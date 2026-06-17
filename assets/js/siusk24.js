var siusk24_terminals_loading = false;

(function ($) {

	$( document ).ready(
		function () {

			console.log( 'siusk_24_js' );

			$( 'body' ).on(
				'click',
				'.shipping_method',
				function () {
					if (typeof (siusk24int_terminal_reference) !== 'undefined') {
						if ($( this ).attr( 'value' ) !== siusk24int_terminal_reference) {
							$( '.tmjs-container' ).hide();
						}
					}
				}
			);

			$( 'body' ).on(
				'load-siusk24-terminals',
				function () {
					if ($( '.tmjs-container' ).length == 0 && siusk24_terminals_loading === false) {
						loadSiusk24Mapping();
					}
				}
			);

			$( 'body' ).on(
				"updated_checkout updated_shipping_method",
				function () {
					if ($( '.tmjs-container' ).length > 0 && siusk24_terminals_loading === false) {
						removeSiusk24Mapping();
						loadSiusk24Mapping();
					}
				}
			);

		}
	);

	function siusk24_getPostcode() {
		var postcode;
		if ($( '#ship-to-different-address-checkbox' ).length && $( '#ship-to-different-address-checkbox' ).is( ':checked' )) {
			if ($( "#shipping_postcode" ).length && $( "#shipping_postcode" ).val()) {
				postcode = $( "#shipping_postcode" ).val();
			}
		} else {
			if ($( "#billing_postcode" ).length && $( "#billing_postcode" ).val()) {
				postcode = $( "#billing_postcode" ).val();
			} else if ($( "#calc_shipping_postcode" ).length && $( "#calc_shipping_postcode" ).val()) {
				postcode = $( "#calc_shipping_postcode" ).val();
			}
		}
		return postcode;
	}

	function siusk24_getCity() {
		var city;
		if ($( '#ship-to-different-address-checkbox' ).length && $( '#ship-to-different-address-checkbox' ).is( ':checked' )) {
			if ($( "#shipping_city" ).length && $( "#shipping_city" ).val()) {
				city = $( "#shipping_city" ).val();
			}
		} else {
			if ($( "#billing_city" ).length && $( "#billing_city" ).val()) {
				city = $( "#billing_city" ).val();
			} else if ($( "#calc_shipping_city" ).length && $( "#calc_shipping_city" ).val()) {
				city = $( "#calc_shipping_city" ).val();
			}
		}
		return city;
	}

	function siusk24_getAddress() {
		var address;
		if ($( '#ship-to-different-address-checkbox' ).length && $( '#ship-to-different-address-checkbox' ).is( ':checked' )) {
			if ($( "#shipping_address_1" ).length && $( "#shipping_address_1" ).val()) {
				address = $( "#shipping_address_1" ).val();
			}
		} else {
			if ($( "#billing_city" ).length && $( "#billing_city" ).val()) {
				address = $( "#billing_address_1" ).val();
			} else if ($( "#calc_shipping_address_1" ).length && $( "#calc_shipping_address_1" ).val()) {
				address = $( "#calc_shipping_address_1" ).val();
			}
		}
		return address;
	}

	function removeSiusk24Mapping() {
		if (typeof tmjs !== 'undefined' && siusk24_terminals_loading === false) {
			var container = document.getElementById( tmjs.containerId );
			if (document.body.contains( container )) {
				container.remove();
			}

			var modal = document.getElementById( tmjs.containerId + "_modal" );
			if (document.body.contains( modal )) {
				modal.remove();
			}

			window['tmjs'] = null;
		}
	}

	function loadSiusk24Mapping() {
		siusk24_terminals_loading = true;
		let isModalReady          = false;
		var tmjs                  = new Siusk24Mapping( siusk24Settings.api_url + '/api/v1' );

		tmjs.sub(
			'terminal-selected',
			function (data) {
				$( 'input[name="order[receiver_attributes][parcel_machine_id]"]' ).val( data.id );
				$( '#order_receiver_attributes_terminal_address' ).val( data.name + ", " + data.address );
				$( '.receiver_parcel_machine_address_filled' ).text( '' );
				$( '.receiver_parcel_machine_address_filled' ).append(
					'<div class="d-inline-flex" style="margin-top: 5px;">' +
					'<img class="my-auto mx-0 me-2" src="' + siusk24Settings.api_url + '/default_icon_icon.svg" width="25" height="25">' +
					'<h5 class="my-auto mx-0">' + data.address + ", " + data.zip + ", " + data.city + '</h5></div>' +
					'<br><a class="select_parcel_btn select_parcel_href" data-remote="true" href="#">Pakeisti</a>'
				)
				$( '.receiver_parcel_machine_address_filled' ).show();
				$( '.receiver_parcel_machine_address_notfilled' ).hide();

				tmjs.publish( 'close-map-modal' );
			}
		);

		tmjs_country_code = $( '#order_receiver_attributes_country_code' ).val();
		tmjs_identifier   = $( '#order_receiver_attributes_service_identifier' ).val();

		tmjs.setImagesPath( siusk24data.images_path );
		tmjs.init(
			{
				country_code: siusk24Settings.country,
				identifier: siusk24Settings.identifier,
				city: siusk24_getCity(),
				postal_code: siusk24_getPostcode(),
				receiver_address: siusk24_getAddress(),
				max_distance: siusk24Settings.max_distance
			}
		);

		window['tmjs'] = tmjs;

		tmjs.setTranslation(
			{
				modal_header: siusk24data.text_map,
				terminal_list_header: siusk24data.text_list,
				seach_header: siusk24data.text_search,
				search_btn: siusk24data.text_search,
				modal_open_btn: siusk24data.text_select_terminal,
				geolocation_btn: siusk24data.text_my_loc,
				your_position: 'Distance calculated from this point',
				nothing_found: siusk24data.text_not_found,
				no_cities_found: siusk24data.text_no_city,
				geolocation_not_supported: 'Geolocation not supported',

				// Unused strings
				search_placeholder: siusk24data.text_enter_address,
				workhours_header: 'Work hours',
				contacts_header: 'Contacts',
				select_pickup_point: '',
				no_pickup_points: 'No terminal',
				select_btn: siusk24data.text_select,
				back_to_list_btn: siusk24data.text_reset,
				no_information: siusk24data.text_not_found
			}
		);

		tmjs.sub(
			'tmjs-ready',
			function (t) {
				t.map.ZOOM_SELECTED = 8;
				isModalReady        = true;
				$( '.spinner-border' ).hide();
				$( '.select_parcel_btn' ).removeClass( 'disabled' ).html( siusk24data.text_select_terminal );
				siusk24_terminals_loading = false;

				var selected_postcode = siusk24_getPostcode();
				t.dom.searchNearest( selected_postcode );
				t.dom.UI.modal.querySelector( '.tmjs-search-input' ).value = selected_postcode;
			}
		);

		$( document ).on(
			'click',
			'.select_parcel_btn',
			function (e) {
				e.preventDefault();
				if ( ! isModalReady) {
					return;
				}
				tmjs.publish( 'open-map-modal' );
				coords = {lng: $( '.receiver_coords' ).attr( 'value-x' ), lat: $( '.receiver_coords' ).attr( 'value-y' )};
				if (coords != undefined) {
					tmjs.map.addReferencePosition( coords );
					tmjs.dom.renderTerminalList( tmjs.map.addDistance( coords ), true )
				}
			}
		);

	}

})( jQuery );