/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

const render = () => {};

registerPlugin( 'siusk24_block', {
	render,
	scope: 'woocommerce-checkout',
} );
