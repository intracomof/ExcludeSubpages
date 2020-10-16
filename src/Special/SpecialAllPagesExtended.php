<?php
namespace ExcludeSubpages\Special;

use SpecialAllPages;
use DerivativeContext;
use HTMLForm;
use Html;
use Title;

class SpecialAllPagesExtended extends SpecialAllPages {

	protected $hideSubpages;

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$defaultHideSubpages = true;
		if ( $this->getConfig()->has( 'HideSubpages' ) ) {
			$defaultHideSubpages = $this->getConfig()->get( 'HideSubpages' );
		}

		$this->hideSubpages = $defaultHideSubpages;
		/**
		 *
		 * If form was sent (we can recognize this by namespace query param),
		 * but there is no hidesubpages param then we can assume that hidesubpages checkbox was unchecked
		 */
		if ( $this->getRequest()->getInt( 'namespace', -100 ) >= 0) {
			$this->hideSubpages = $this->getRequest()->getBool( 'hidesubpages', 0 );
		}
		parent::execute( $par );
	}

	/**
	 * @param int $namespace Namespace (Default NS_MAIN)
	 * @param string|false $from List all pages from this name (default false)
	 * @param string|false $to List all pages to this name (default false)
	 * @param bool $hideredirects Don't show redirects (default false)
	 */
	function showChunk( $namespace = NS_MAIN, $from = false, $to = false, $hideredirects = false ) {
		$output = $this->getOutput();

		$fromList = $this->getNamespaceKeyAndText( $namespace, $from );
		$toList = $this->getNamespaceKeyAndText( $namespace, $to );
		$namespaces = $this->getContext()->getLanguage()->getNamespaces();
		$n = 0;
		$prevTitle = null;

		if ( !$fromList || !$toList ) {
			$out = $this->msg( 'allpagesbadtitle' )->parseAsBlock();
		} elseif ( !array_key_exists( $namespace, $namespaces ) ) {
			// Show errormessage and reset to NS_MAIN
			$out = $this->msg( 'allpages-bad-ns', $namespace )->parse();
			$namespace = NS_MAIN;
		} else {
			list( $namespace, $fromKey, $from ) = $fromList;
			list( , $toKey, $to ) = $toList;

			$dbr = wfGetDB( DB_REPLICA );
			$filterConds = [ 'page_namespace' => $namespace ];
			if ( $hideredirects ) {
				$filterConds['page_is_redirect'] = 0;
			}

			$conds = $filterConds;
			$conds[] = 'page_title >= ' . $dbr->addQuotes( $fromKey );
			if ( $toKey !== "" ) {
				$conds[] = 'page_title <= ' . $dbr->addQuotes( $toKey );
			}

			$res = $dbr->select( 'page',
				[ 'page_namespace', 'page_title', 'page_is_redirect', 'page_id' ],
				$conds,
				__METHOD__,
				[
					'ORDER BY' => 'page_title',
					'LIMIT' => $this->maxPerPage + 1,
					'USE INDEX' => 'name_title',
				]
			);

			$linkRenderer = $this->getLinkRenderer();
			if ( $res->numRows() > 0 ) {
				$out = Html::openElement( 'ul', [ 'class' => 'mw-allpages-chunk' ] );

				while ( ( $n < $this->maxPerPage ) && ( $s = $res->fetchObject() ) ) {
					$t = Title::newFromRow( $s );
					if ( $t ) {
						if ( $this->hideSubpages && $t->isSubpage() ) {
							continue;
						}
						$out .= '<li' .
							( $s->page_is_redirect ? ' class="allpagesredirect"' : '' ) .
							'>' .
							$linkRenderer->makeLink( $t ) .
							"</li>\n";
					} else {
						$out .= '<li>[[' . htmlspecialchars( $s->page_title ) . "]]</li>\n";
					}
					$n++;
				}
				$out .= Html::closeElement( 'ul' );

				if ( $res->numRows() > 2 ) {
					// Only apply CSS column styles if there's more than 2 entries.
					// Otherwise, rendering is broken as "mw-allpages-body"'s CSS column count is 3.
					$out = Html::rawElement( 'div', [ 'class' => 'mw-allpages-body' ], $out );
				}
			} else {
				$out = '';
			}

			if ( $fromKey !== '' && !$this->including() ) {
				# Get the first title from previous chunk
				$prevConds = $filterConds;
				$prevConds[] = 'page_title < ' . $dbr->addQuotes( $fromKey );
				$prevKey = $dbr->selectField(
					'page',
					'page_title',
					$prevConds,
					__METHOD__,
					[ 'ORDER BY' => 'page_title DESC', 'OFFSET' => $this->maxPerPage - 1 ]
				);

				if ( $prevKey === false ) {
					# The previous chunk is not complete, need to link to the very first title
					# available in the database
					$prevKey = $dbr->selectField(
						'page',
						'page_title',
						$prevConds,
						__METHOD__,
						[ 'ORDER BY' => 'page_title' ]
					);
				}

				if ( $prevKey !== false ) {
					$prevTitle = Title::makeTitle( $namespace, $prevKey );
				}
			}
		}

		if ( $this->including() ) {
			$output->addHTML( $out );
			return;
		}

		$navLinks = [];
		$self = $this->getPageTitle();

		$linkRenderer = $this->getLinkRenderer();
		// Generate a "previous page" link if needed
		if ( $prevTitle ) {
			$query = [ 'from' => $prevTitle->getText() ];

			if ( $namespace ) {
				$query['namespace'] = $namespace;
			}

			if ( $hideredirects ) {
				$query['hideredirects'] = $hideredirects;
			}

			if ( $hideredirects ) {
				$query['hidesubpages'] = $this->hideSubpages;
			}

			$navLinks[] = $linkRenderer->makeKnownLink(
				$self,
				$this->msg( 'prevpage', $prevTitle->getText() )->text(),
				[],
				$query
			);

		}

		// Generate a "next page" link if needed
		if ( $n == $this->maxPerPage && $s = $res->fetchObject() ) {
			# $s is the first link of the next chunk
			$t = Title::makeTitle( $namespace, $s->page_title );
			$query = [ 'from' => $t->getText() ];

			if ( $namespace ) {
				$query['namespace'] = $namespace;
			}

			if ( $hideredirects ) {
				$query['hideredirects'] = $hideredirects;
			}

			$navLinks[] = $linkRenderer->makeKnownLink(
				$self,
				$this->msg( 'nextpage', $t->getText() )->text(),
				[],
				$query
			);
		}

		$this->outputHTMLForm( $namespace, $from, $to, $hideredirects );

		if ( count( $navLinks ) ) {
			// Add pagination links
			$pagination = Html::rawElement( 'div',
				[ 'class' => 'mw-allpages-nav' ],
				$this->getLanguage()->pipeList( $navLinks )
			);

			$output->addHTML( $pagination );
			$out .= Html::element( 'hr' ) . $pagination; // Footer
		}

		$output->addHTML( $out );
	}

	/**
	 * Outputs the HTMLForm used on this page
	 *
	 * @param int $namespace A namespace constant (default NS_MAIN).
	 * @param string $from DbKey we are starting listing at.
	 * @param string $to DbKey we are ending listing at.
	 * @param bool $hideRedirects Don't show redirects  (default false)
	 */
	protected function outputHTMLForm( $namespace = NS_MAIN,
									   $from = '', $to = '', $hideRedirects = false
	) {
		$miserMode = (bool)$this->getConfig()->get( 'MiserMode' );
		$formDescriptor = [
			'from' => [
				'type' => 'text',
				'name' => 'from',
				'id' => 'nsfrom',
				'size' => 30,
				'label-message' => 'allpagesfrom',
				'default' => str_replace( '_', ' ', $from ),
			],
			'to' => [
				'type' => 'text',
				'name' => 'to',
				'id' => 'nsto',
				'size' => 30,
				'label-message' => 'allpagesto',
				'default' => str_replace( '_', ' ', $to ),
			],
			'namespace' => [
				'type' => 'namespaceselect',
				'name' => 'namespace',
				'id' => 'namespace',
				'label-message' => 'namespace',
				'all' => null,
				'default' => $namespace,
			],
			'hideredirects' => [
				'type' => 'check',
				'name' => 'hideredirects',
				'id' => 'hidredirects',
				'label-message' => 'allpages-hide-redirects',
				'value' => $hideRedirects,
			],
			'hidesubpages' => [
				'type' => 'check',
				'name' => 'hidesubpages',
				'id' => 'hidesubpages',
				'label-message' => 'allpages-exclude-subpages-checkbox-label',
				'value' => $this->hideSubpages,
				'default' => $this->hideSubpages
			],
		];

		if ( $miserMode ) {
			unset( $formDescriptor['hideredirects'] );
		}

		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle() ); // Remove subpage
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $context );
		$htmlForm
			->setMethod( 'get' )
			->setWrapperLegendMsg( 'allpages' )
			->setSubmitTextMsg( 'allpagessubmit' )
			->prepareForm()
			->displayForm( false );
	}

}
