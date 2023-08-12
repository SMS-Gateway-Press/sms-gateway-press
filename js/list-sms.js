document.addEventListener( 'DOMContentLoaded', () => {
    const the_list = document.getElementById( 'the-list' );

    setInterval( () => {
        const id_list = [];

        for ( const tr of the_list.children ) {
            const post_id = tr.getAttribute( 'id' ).slice( 5 );
            id_list.push( post_id );
        }

        const requestBody = new FormData();
        requestBody.set( 'id_list', id_list );
        requestBody.set( 'action', app.action );
        requestBody.set( 'nonce', app.nonce );

        const options = {
            method: 'POST',
            body: requestBody,
        };

        fetch( app.url, options ).then( response => {
            response.json().then( json => {
                for ( const post_id in json.data ) {
                    const post_data = json.data[ post_id ];
                    const tr = document.getElementById( `post-${post_id}` );

                    const status_column = tr.querySelector( '.column-status' );
                    const sent_at_column = tr.querySelector( '.column-_sent_at' );
                    const delivered_at_column = tr.querySelector( '.column-_delivered_at' );

                    status_column.innerHTML = post_data.status;
                    sent_at_column.innerHTML = post_data.sent_at;
                    delivered_at_column.innerHTML = post_data.delivered_at;
                }
            } );
        } );
    }, 1000 );
} );