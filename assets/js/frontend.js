/**
 * Frontend JS for Kayce Custom Archive Sections.
 *
 * Repositions `before_content` sections that ended up in the wrong DOM
 * position when no theme-specific PHP hook fired at the correct level.
 *
 * The function runs in a loop per section so that a single pass can handle
 * multi-step repositioning. Example: on ColorMag the fallback loop_start hook
 * places the section inside `.cm-posts`; one pass moves it before `.cm-posts`
 * (now inside `#cm-primary`); the next pass detects the sidebar sibling and
 * moves it before the `.cm-row` columns container — full width, outside both
 * the primary column and the sidebar.
 *
 * Two conditions trigger a move on each pass:
 *
 *  Case 1 — Inside a posts-loop container.
 *    Detection: parent has direct <article> children.
 *    Move: section → before its parent.
 *
 *  Case 2 — Inside a primary column next to a sidebar.
 *    Detection: parent's parent (the row) has a child that is a sidebar /
 *    complementary area (ARIA role, or id/class patterns).
 *    Move: section → before the row, so it becomes full-width.
 *
 * When a theme hook already placed the section correctly (e.g. Astra's
 * astra_header_after, or the FSE PHP path), neither condition triggers and
 * the loop exits immediately, leaving the section untouched.
 */
( function () {
	'use strict';

	/**
	 * Return true if the element looks like a sidebar / complementary area.
	 *
	 * @param {Element} el
	 * @return {boolean}
	 */
	function isSidebarElement( el ) {
		if ( ! el ) {
			return false;
		}
		if ( el.getAttribute( 'role' ) === 'complementary' ) {
			return true;
		}
		var id  = el.id || '';
		var cls = typeof el.className === 'string' ? el.className : '';
		return /\b(secondary|sidebar|widget-area|cm-secondary|aside)\b/i.test( id + ' ' + cls );
	}

	function repositionBeforeContentSections() {
		var sections = document.querySelectorAll( '.kcas-archive-sections--before_content' );
		if ( ! sections.length ) {
			return;
		}

		sections.forEach( function ( section ) {
			// Loop until no move was made in a full pass, or a safety limit is hit.
			var maxPasses = 8;

			for ( var pass = 0; pass < maxPasses; pass++ ) {
				var parent = section.parentElement;
				if ( ! parent ) {
					break;
				}

				// ── Case 1: section is inside a posts-loop container ──────────────
				var articles = parent.querySelectorAll( ':scope > article' );
				if ( articles.length ) {
					var grandParent = parent.parentElement;
					if ( grandParent ) {
						grandParent.insertBefore( section, parent );
						// Continue the loop — the new position may still need fixing.
						continue;
					}
					break;
				}

				// ── Case 2: section is inside a column next to a sidebar ──────────
				var row = parent.parentElement;
				if ( ! row ) {
					break;
				}

				var hasSidebarSibling = false;
				var children = row.children;
				for ( var i = 0; i < children.length; i++ ) {
					if ( children[ i ] !== parent && isSidebarElement( children[ i ] ) ) {
						hasSidebarSibling = true;
						break;
					}
				}

				if ( hasSidebarSibling ) {
					var rowParent = row.parentElement;
					if ( rowParent ) {
						rowParent.insertBefore( section, row );
						// Continue the loop — check if the new position also needs fixing.
						continue;
					}
					break;
				}

				// Neither condition triggered — section is correctly placed. Stop.
				break;
			}
		} );
	}

	// Run before first paint to minimise layout shift.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', repositionBeforeContentSections );
	} else {
		repositionBeforeContentSections();
	}

}() );
