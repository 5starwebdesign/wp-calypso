/**
 * Internal dependencies
 */
import getSiteBlocksLastPage from 'state/selectors/get-site-blocks-last-page';

describe( 'getSiteBlocksLastPage()', () => {
	test( 'should return the last page', () => {
		const prevState = {
			reader: {
				siteBlocks: {
					lastPage: 4,
				},
			},
		};
		const nextState = getSiteBlocksLastPage( prevState );
		expect( nextState ).toEqual( 4 );
	} );

	test( 'should return null if there is no last page yet', () => {
		const prevState = {
			reader: {
				siteBlocks: {
					lastPage: null,
				},
			},
		};
		const nextState = getSiteBlocksLastPage( prevState );
		expect( nextState ).toEqual( null );
	} );
} );
