/**
 * Frontend JS for Kayce Custom Archive Sections.
 *
 * Handles one job: if a `before_content` section ended up inside a posts-loop
 * container (because no theme-specific hook fired and the fallback placed it
 * at loop_start), move it to *before* that container.
 *
 * This covers any classic theme not in the PHP hook cascade — ColorMag,
 * GeneratePress, Blocksy without the action hook, custom themes, etc.
 *
 * Detection: a section needs repositioning only when its direct parent also
 * contains <article> children, which indicates it landed inside the posts grid.
 * When a theme hook placed it correctly (outside the grid), no articles will
 * be siblings and we leave it alone.
 */
( function () {
	'use strict';

	function repositionBeforeContentSections() {
		var sections = document.querySelectorAll( '.kcas-archive-sections--before_content' );
		if ( ! sections.length ) {
			return;
		}

		sections.forEach( function ( section ) {
			var parent = section.parentElement;
			if ( ! parent ) {
				return;
			}

			// Check if the parent is a posts loop wrapper by looking for
			// direct <article> children — a reliable signal across all themes.
			var articles = parent.querySelectorAll( ':scope > article' );
			if ( ! articles.length ) {
				// Section is already outside the loop — correctly placed. Do nothing.
				return;
			}

			// The section landed inside the posts container. Move it to just
			// before that container so it sits between the archive header and posts.
			var grandParent = parent.parentElement;
			if ( grandParent ) {
				grandParent.insertBefore( section, parent );
			}
		} );
	}

	// Run before the first paint to minimise layout shift.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', repositionBeforeContentSections );
	} else {
		// DOM is already ready (script loaded late / deferred).
		repositionBeforeContentSections();
	}

}() );
