/**
 * Admin JS for Kayce Custom Archive Sections.
 *
 * Shows / hides the specific-categories selector based on the
 * currently chosen location radio button.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var locationOptions = document.getElementById( 'kcas-location-options' );
		var categoriesRow   = document.getElementById( 'kcas-categories-row' );

		if ( ! locationOptions || ! categoriesRow ) {
			return;
		}

		/**
		 * Read the selected location radio and show / hide the category list.
		 */
		function syncCategoriesVisibility() {
			var selected = locationOptions.querySelector( 'input[name="kcas_location"]:checked' );
			var show     = selected && selected.value === 'specific_categories';
			categoriesRow.style.display = show ? '' : 'none';
		}

		// Run on every change inside the location group.
		locationOptions.addEventListener( 'change', syncCategoriesVisibility );

		// Run once on load to reflect a previously saved value.
		syncCategoriesVisibility();
	} );
}() );
