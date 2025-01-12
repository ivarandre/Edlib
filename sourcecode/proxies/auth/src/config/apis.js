import { env } from '@cerpus/edlib-node-utils';

export default {
    externalAuth: {
        url: env('AUTHAPI_URL', 'https://auth.local'),
        clientId: env(
            'AUTHAPI_CLIENT_ID',
            'b629aec5-58fb-42df-b58e-753affe8f868'
        ),
        secret: env('AUTHAPI_SECRET', '91bbc285-a57a-405c-97cb-c9549ced42f0'),
    },
    edlibAuth: {
        url: env('EDLIB_AUTH_URL', 'http://authapi'),
    },
};
