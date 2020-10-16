<?php
namespace ExcludeSubpages\Hook\SpecialPageBeforeExecute;

use ExcludeSubpages\Special\SpecialAllPagesExtended;
use SpecialPage;

class ModifySpecialAllPages {

	/**
	 * This hook is called before SpecialPage::execute.
	 *
	 * @since 1.35
	 *
	 * @param SpecialPage $special
	 * @param string|null $subPage Subpage string, or null if no subpage was specified
	 * @return bool|void True or no return value to continue or false to prevent execution
	 */
	public static function callback( $special, $subPage ) {
		if ( $special->getName() !== 'Allpages' ) {
			return true;
		}
		$special = new SpecialAllPagesExtended();
		$special->execute( $subPage );
		return false;
	}
}
