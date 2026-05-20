/**
 * Admin JS for Kayce Custom Archive Sections.
 *
 * 1. Location card grid — syncs is-selected class on `.kcas-location-card`.
 * 2. Button groups — syncs is-selected class on `.kcas-btn-option`.
 * 3. Category row — shows / hides when "Specific categories" is selected.
 * 4. Category search — live-filters the category list.
 * 5. Category checkboxes — syncs is-checked class on `.kcas-cat-item`.
 * 6. Quick Edit — populates fields from hidden data-* spans.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		// ── 1. Location card grid ─────────────────────────────────────────────
		var locationGrid = document.getElementById( 'kcas-location-options' );

		if ( locationGrid ) {
			// Sync is-selected on every card based on the hidden radio.
			function syncLocationCards() {
				var cards = locationGrid.querySelectorAll( '.kcas-location-card' );
				cards.forEach( function ( card ) {
					var radio = card.querySelector( 'input[type="radio"]' );
					card.classList.toggle( 'is-selected', !! ( radio && radio.checked ) );
				} );
			}

			// Allow clicking anywhere on the card — toggle the radio + reselect.
			locationGrid.querySelectorAll( '.kcas-location-card' ).forEach( function ( card ) {
				card.addEventListener( 'click', function () {
					var radio = card.querySelector( 'input[type="radio"]' );
					if ( radio ) {
						radio.checked = true;
						// Dispatch change so the categories-row listener fires.
						radio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
					syncLocationCards();
				} );
			} );

			syncLocationCards();
		}

		// ── 2. Segmented button groups (Position + Visibility) ────────────────
		document.querySelectorAll( '.kcas-btn-group' ).forEach( function ( group ) {
			function syncBtnGroup() {
				group.querySelectorAll( '.kcas-btn-option' ).forEach( function ( btn ) {
					var radio = btn.querySelector( 'input[type="radio"]' );
					btn.classList.toggle( 'is-selected', !! ( radio && radio.checked ) );
				} );
			}

			group.querySelectorAll( '.kcas-btn-option' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var radio = btn.querySelector( 'input[type="radio"]' );
					if ( radio ) {
						radio.checked = true;
					}
					syncBtnGroup();
				} );
			} );

			syncBtnGroup();
		} );

		// ── 3. Location radio → show/hide dependent sections ────────────────
		var categoriesRow = document.getElementById( 'kcas-categories-row' );
		var positionRow   = document.getElementById( 'kcas-position-row' );
		// The .kcas-divider immediately before the Position section.
		var positionDivider = positionRow ? positionRow.previousElementSibling : null;

		function syncLocationDependents() {
			if ( ! locationGrid ) {
				return;
			}
			var selected   = locationGrid.querySelector( 'input[name="kcas_location"]:checked' );
			var value      = selected ? selected.value : '';
			var hasLocation = value !== '';

			// Categories row — only when Specific Categories is chosen.
			if ( categoriesRow ) {
				categoriesRow.style.display = ( value === 'specific_categories' ) ? '' : 'none';
			}

			// Position row (and its preceding divider) — hidden when Disabled.
			if ( positionRow ) {
				positionRow.style.display = hasLocation ? '' : 'none';
			}
			if ( positionDivider && positionDivider.classList.contains( 'kcas-divider' ) ) {
				positionDivider.style.display = hasLocation ? '' : 'none';
			}
		}

		if ( locationGrid ) {
			locationGrid.addEventListener( 'change', syncLocationDependents );
			syncLocationDependents();
		}

		// ── 4. Category search filter ─────────────────────────────────────────
		var catSearch = document.getElementById( 'kcas-cat-search' );
		if ( catSearch ) {
			catSearch.addEventListener( 'input', function () {
				var term  = this.value.toLowerCase().trim();
				var items = document.querySelectorAll( '.kcas-cat-item' );
				items.forEach( function ( item ) {
					var name = item.querySelector( '.kcas-cat-name' );
					var text = name ? name.textContent.toLowerCase() : item.textContent.toLowerCase();
					item.style.display = ( ! term || text.indexOf( term ) !== -1 ) ? '' : 'none';
				} );
			} );
		}

		// ── 5. Category item — sync is-checked class ──────────────────────────
		document.querySelectorAll( '.kcas-cat-item input[type="checkbox"]' ).forEach( function ( chk ) {
			chk.addEventListener( 'change', function () {
				var item = chk.closest( '.kcas-cat-item' );
				if ( item ) {
					item.classList.toggle( 'is-checked', chk.checked );
				}
			} );
		} );

	} );

	// ── 6. Quick Edit — populate fields from hidden data-* spans ─────────────
	// Hooks into WordPress's built-in inlineEditPost.edit function.
	if ( typeof inlineEditPost !== 'undefined' ) {
		var wpInlineEdit = inlineEditPost.edit;

		inlineEditPost.edit = function ( id ) {
			// Call the original WordPress inline-edit function first.
			wpInlineEdit.apply( this, arguments );

			var postId = ( typeof id === 'object' ) ? this.getId( id ) : id;
			var row    = document.getElementById( 'post-' + postId );
			if ( ! row ) {
				return;
			}

			// Read values from the hidden span output in render_list_column().
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
