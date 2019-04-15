/**
 * @format
 * @jest-environment jsdom
 */
/**
 * External dependencies
 */
import { mount } from 'enzyme';

/**
 * Internal dependencies
 */
import TransactionAmount from '../transaction-amount';

// const translate = x => x;

describe( 'TransactionAmount', () => {
	const transaction = {
		subtotal: '$36.00',
		tax: '$2.48',
		amount: '$38.48',
		items: [
			{
				raw_tax: 2.48,
			},
		],
	};

	const upcoming = { amount: '€38.48' };

	test( 'amount', () => {} );
	test( 'tax exempt', () => {} );
	test( 'tax inclusive', () => {} );
	test( 'tax exclusive', () => {} );
	test( 'tax applicable', () => {} );
} );
