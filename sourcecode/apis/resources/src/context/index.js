import resource from '../repositories/resource.js';
import resourceVersion from '../repositories/resourceVersion.js';
import services from './services/index.js';
import resourceGroup from '../repositories/resourceGroup.js';
import resourceVersionCollaborator from '../repositories/resourceVersionCollaborator.js';
import job from '../repositories/job.js';
import trackingResourceVersion from '../repositories/trackingResourceVersion.js';
import resourceCollaborator from '../repositories/resourceCollaborator.js';

export const buildRawContext = (req = {}, res = {}, { pubSubConnection }) => ({
    services: services(req, res),
    db: {
        resource: resource(),
        resourceVersion: resourceVersion(),
        resourceVersionCollaborator: resourceVersionCollaborator(),
        resourceGroup: resourceGroup(),
        job: job(),
        trackingResourceVersion: trackingResourceVersion(),
        resourceCollaborator: resourceCollaborator(),
    },
    pubSubConnection,
});

const getContext = (req, res, { pubSubConnection }) => ({
    user: req.user,
    res,
    req,
    ...buildRawContext(req, res, { pubSubConnection }),
});

export default getContext;
