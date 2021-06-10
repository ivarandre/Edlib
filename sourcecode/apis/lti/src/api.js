import { setupApi } from '@cerpus/edlib-node-utils';
import router from './routes/index.js';
import errorReportingConfig from './config/errorReporting.js';
import { buildRawContext } from './context/index.js';
import fileParserService from './services/fileParser.js';
import consumerService from './services/consumer.js';
import sync from './subscribers/sync.js';
import { pubsub } from '@cerpus/edlib-node-utils';

const start = async () => {
    const pubSubConnection = await pubsub.setup();
    const context = buildRawContext({}, {}, { pubSubConnection });

    // Set consumers from configuration file
    const { consumers } = fileParserService.getConfigurationValuesFromSetupFile(
        'consumers.yaml'
    );

    if (Array.isArray(consumers)) {
        await Promise.all(
            consumers
                .filter((consumer) => consumer.key && consumer.secret)
                .map(({ key, secret }) =>
                    consumerService.createOrUpdate(context, key, secret)
                )
        );
    }

    await Promise.all(
        [
            {
                exchangeName: '__internal_edlibLti_sync',
                subscriptionName: '__internal_edlibLti_sync-sync',
                handler: sync,
            },
        ].map((subscriber) => {
            const handler = subscriber.handler({ pubSubConnection });

            return pubsub.subscribe(
                pubSubConnection,
                subscriber.exchangeName,
                subscriber.subscriptionName,
                async (msg) => {
                    console.log('meesage');
                    await handler(JSON.parse(msg.content));
                }
            );
        })
    );

    setupApi(async () => router({ pubSubConnection }), {
        errorReportingConfig,
    });
};

start();
