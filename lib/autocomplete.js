( function () {
	'use strict';

	if ( 'undefined' === typeof relevanssiAutocomplete ) {
		return;
	}

	var settings = relevanssiAutocomplete;

	function debounce( fn, delay ) {
		var timer = null;
		return function () {
			var args = arguments;
			var context = this;
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				fn.apply( context, args );
			}, delay );
		};
	}

	function createIcon() {
		var icon = document.createElement( 'div' );
		icon.className = 'relevanssi-autocomplete-icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		icon.textContent = '▣';
		return icon;
	}

	function createThumbnail( url ) {
		var img = document.createElement( 'img' );
		img.className = 'relevanssi-autocomplete-thumbnail';
		img.src = url;
		img.alt = '';
		return img;
	}

	function buildRow( result, index ) {
		var row = document.createElement( 'div' );
		row.className = 'relevanssi-autocomplete-row';
		row.setAttribute( 'role', 'option' );
		row.id = 'relevanssi-autocomplete-option-' + index;
		row.tabIndex = -1;

		if ( 'product' === result.type && result.thumbnail ) {
			row.appendChild( createThumbnail( result.thumbnail ) );
		} else {
			row.appendChild( createIcon() );
		}

		var title = document.createElement( 'div' );
		title.className = 'relevanssi-autocomplete-title';
		title.textContent = result.title;
		row.appendChild( title );

		row.addEventListener( 'click', function () {
			window.location.href = result.url;
		} );

		return row;
	}

	function buildFooterRow( query, form, index ) {
		var row = document.createElement( 'div' );
		row.className = 'relevanssi-autocomplete-row relevanssi-autocomplete-footer';
		row.setAttribute( 'role', 'option' );
		row.id = 'relevanssi-autocomplete-option-' + index;
		row.tabIndex = -1;

		var label = document.createElement( 'div' );
		label.className = 'relevanssi-autocomplete-title';
		label.textContent = 'View all results for "' + query + '"';
		row.appendChild( label );

		row.addEventListener( 'click', function () {
			if ( form ) {
				form.submit();
			}
		} );

		return row;
	}

	function setupField( input ) {
		var form = input.form;

		var postType = '';
		if ( form ) {
			var postTypeField = form.querySelector( 'input[name="post_type"]' );
			if ( postTypeField ) {
				postType = postTypeField.value;
			}
		}

		var wrapper = document.createElement( 'div' );
		wrapper.className = 'relevanssi-autocomplete-wrapper';
		input.parentNode.insertBefore( wrapper, input );
		wrapper.appendChild( input );

		var dropdown = document.createElement( 'div' );
		dropdown.className = 'relevanssi-autocomplete';
		dropdown.setAttribute( 'role', 'listbox' );
		dropdown.style.display = 'none';
		wrapper.appendChild( dropdown );

		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'autocomplete', 'off' );

		var activeIndex = -1;
		var rowCount = 0;
		var latestQuery = '';

		function closeDropdown() {
			dropdown.style.display = 'none';
			dropdown.textContent = '';
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			activeIndex = -1;
			rowCount = 0;
		}

		function setActive( index ) {
			var rows = dropdown.querySelectorAll( '.relevanssi-autocomplete-row' );
			for ( var i = 0; i < rows.length; i++ ) {
				rows[ i ].classList.remove( 'is-active' );
			}
			if ( index >= 0 && index < rows.length ) {
				rows[ index ].classList.add( 'is-active' );
				input.setAttribute( 'aria-activedescendant', rows[ index ].id );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
			activeIndex = index;
		}

		function renderResults( query, results ) {
			dropdown.textContent = '';
			var index = 0;

			results.forEach( function ( result ) {
				dropdown.appendChild( buildRow( result, index ) );
				index++;
			} );

			dropdown.appendChild( buildFooterRow( query, form, index ) );
			rowCount = index + 1;

			dropdown.style.display = 'block';
			input.setAttribute( 'aria-expanded', 'true' );
			setActive( -1 );
		}

		function fetchResults( query ) {
			latestQuery = query;

			var url = settings.ajaxUrl
				+ '?action=relevanssi_autocomplete'
				+ '&nonce=' + encodeURIComponent( settings.nonce )
				+ '&q=' + encodeURIComponent( query );

			if ( postType ) {
				url += '&post_type=' + encodeURIComponent( postType );
			}

			fetch( url, { credentials: 'same-origin' } )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Request failed' );
					}
					return response.json();
				} )
				.then( function ( data ) {
					if ( query !== latestQuery ) {
						return;
					}
					if ( ! data || ! data.success ) {
						closeDropdown();
						return;
					}
					renderResults( query, data.data.results || [] );
				} )
				.catch( function () {
					closeDropdown();
				} );
		}

		var onInput = debounce( function () {
			var query = input.value.trim();

			if ( query.length < settings.minChars ) {
				closeDropdown();
				return;
			}

			fetchResults( query );
		}, 250 );

		input.addEventListener( 'input', onInput );

		input.addEventListener( 'keydown', function ( event ) {
			if ( ! rowCount ) {
				return;
			}

			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				setActive( activeIndex < rowCount - 1 ? activeIndex + 1 : 0 );
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				setActive( activeIndex > 0 ? activeIndex - 1 : rowCount - 1 );
			} else if ( 'Enter' === event.key ) {
				if ( activeIndex > -1 ) {
					event.preventDefault();
					var rows = dropdown.querySelectorAll( '.relevanssi-autocomplete-row' );
					rows[ activeIndex ].click();
				}
			} else if ( 'Escape' === event.key ) {
				closeDropdown();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! wrapper.contains( event.target ) ) {
				closeDropdown();
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var inputs = document.querySelectorAll( '#s, input[type="search"], .search-field' );
		var seen = [];

		inputs.forEach( function ( input ) {
			if ( seen.indexOf( input ) > -1 ) {
				return;
			}
			seen.push( input );
			setupField( input );
		} );
	} );
} )();
