import { env } from '@cerpus/edlib-node-utils';

export default {
    version: {
        url: env('VERSIONAPI_URL', 'http://versioningapi:8080'),
    },
    coreInternal: {
        url: env('EDLIBCOMMON_CORE_INTERNAL_URL', 'http://core'),
    },
};
