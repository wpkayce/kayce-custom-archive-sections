/**
 * Admin JS for Kayce Custom Archive Sections.
 *
 * 1. Shows / hides the specific-categories selector (location radio).
 * 2. Filters the category list as the user types in the search box (#15).
 * 3. Populates Quick Edit fields with current row values (#16).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		// ── 1. Location radio → show/hide category picker ────────────────────
		var locationOptions = document.getElementById( 'kcas-location-options' );
		var categoriesRow   = document.getElementById( 'kcas-categories-row' );

		if ( locationOptions && categoriesRow ) {
			function syncCategoriesVisibility() {
				var selected = locationOptions.querySelector( 'input[name="kcas_location"]:checked' );
				var show     = selected && selected.value === 'specific_categories';
				categoriesRow.style.display = show ? '' : 'none';
			}
			locationOptions.addEventListener( 'change', syncCategoriesVisibility );
			syncCategoriesVisibility();
		}

		// ── 2. Category search filter (#15) ───────────────────────────────────
		var catSearch = document.getElementById( 'kcas-cat-search' );
		if ( catSearch ) {
			catSearch.addEventListener( 'input', function () {
				var term   = this.value.toLowerCase().trim();
				var labels = document.querySelectorAll( '.kcas-category-list label' );
				labels.forEach( function ( label ) {
					var text = label.textContent.toLowerCase();
					label.style.display = ( ! term || text.indexOf( term ) !== -1 ) ? '' : 'none';
				} );
			} );
		}

	} );

	// ── 3. Quick Edit — populate fields from hidden data spans (#16) ──────────
	// Hook into WordPress's built-in inlineEditPost.edit function.
	if ( typeof inlineEditPost !== 'undefined' ) {
		var wpInlineEdit = inlineEditPost.edit;

		inlineEditPost.edit = function ( id ) {
			// Call the original WordPress inline edit function first.
			wpInlineEdit.apply( this, arguments );

			var postId = ( typeof id === 'object' ) ? this.getId( id ) : id;
			var row    = document.getElementById( 'post-' + postId );
			if ( ! row ) {
				return;
			}

			// Read data from the hidden span we output in render_list_column.
			var dataSpan = row.querySelector( '.kcas-qe-data' );
			if ( ! dataSpan ) {
				return;
			}

			var active     = dataSpan.getAttribute( 'data-active' );
			var position   = dataSpan.getAttribute( 'data-position' );
			var visibility = dataSpan.getAttribute( 'data-visibility' );

			var editRow = document.getElementById( 'edit-' + postId );
			if ( ! editRow ) {
				return;
			}

			// Active checkbox.
			var activeChk = editRow.querySelector( 'input[name="kcas_active"]' );
			if ( activeChk ) {
				activeChk.checked = ( active === '1' );
			}

			// Position select.
			var positionSel = editRow.querySelector( 'select[name="kcas_position"]' );
			if ( positionSel ) {
				positionSel.value = position || 'before';
			}

			// Visibility select.
			var visibilitySel = editRow.querySelector( 'select[name="kcas_visibility"]' );
			if ( visibilitySel ) {
				visibilitySel.value = visibility || 'all';
			}
		};
	}

}() );
