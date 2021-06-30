import { env } from '@cerpus/edlib-node-utils';

const contentAuthorUrl = env(
    'EDLIBCOMMON_CONTENTAUTHOR_URL',
    'https://contentauthor.local'
);

const edlibUrl = env('EDLIBCOMMON_URL', 'https://api.edlib.local');

export default {
    version: {
        url: env('VERSIONAPI_URL', 'http://versioningapi:8080'),
    },
    elasticsearch: {
        resourceIndexPrefix: 'edlib-resources',
        url: env(
            'EDLIBCOMMON_ELASTICSEARCH_URL',
            'http://elasticsearch-latest:9200'
        ),
    },
    externalResourceAPIS: {
        contentauthor: {
            url: `${contentAuthorUrl}/v1/content`,
            ltiUrl: `${contentAuthorUrl}/lti-content`,
            getAllGroups: ['h5p', 'questionset', 'article', 'game'],
            ltiConsumerKey: env(
                'EDLIBCOMMON_CONTENTAUTHOR_CONSUMER_KEY',
                'h5p'
            ),
            ltiConsumerSecret: env(
                'EDLIBCOMMON_CONTENTAUTHOR_CONSUMER_SECRET',
                'secret2'
            ),
        },
        url: {
            disableVersioning: true,
            url: `http://urlapi/v1/content`,
            ltiUrl: `${edlibUrl}/url/v1/lti-view`,
            ltiConsumerKey: env(
                'EDLIBCOMMON_CONTENTAUTHOR_CONSUMER_KEY',
                'h5p'
            ),
            ltiConsumerSecret: env(
                'EDLIBCOMMON_CONTENTAUTHOR_CONSUMER_SECRET',
                'secret2'
            ),
        },
    },
    coreInternal: {
        url: env('EDLIBCOMMON_CORE_INTERNAL_URL', 'http://core'),
    },
    edlibAuth: {
        url: env('EDLIB_AUTH_URL', 'http://authapi'),
    },
    lti: {
        url: env('LTI_API_URL', 'http://ltiapi'),
    },
};
