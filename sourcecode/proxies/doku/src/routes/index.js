import express from 'express';
import addContextToRequest from '../middlewares/addContextToRequest.js';
import swaggerJSDoc from 'swagger-jsdoc';
import swaggerUi from 'swagger-ui-express';
import dokus from './dokus.js';
import recommender from './recommender.js';
import status from './status.js';
import features from '../config/features.js';
import url from './url.js';

const { Router } = express;

export default async () => {
    const router = Router();
    const apiRouter = Router();

    apiRouter.use(
        '/docs',
        swaggerUi.serve,
        swaggerUi.setup(
            swaggerJSDoc({
                swaggerDefinition: {
                    basePath: '/dokus',
                },
                apis: ['./src/routes/**/*.js'],
            })
        )
    );

    /**
     * @swagger
     *
     *  /:
     *      get:
     *          description: home
     *          produces:
     *              - application/json
     *          responses:
     *              200:
     *                  description: Home
     */
    apiRouter.get('/', (req, res) => {
        res.json({
            message: 'Welcome to the EdLib Doku API',
        });
    });

    apiRouter.use(await dokus());
    apiRouter.use(await recommender());
    apiRouter.use(await status());
    apiRouter.use(await url());
    apiRouter.get('/features', (req, res) => {
        res.json(features);
    });

    router.get('/resources/_ah/health', (req, res) => {
        const probe = req.query.probe;

        if (probe === 'liveness') {
            res.send('ok');
        } else if (probe === 'readiness') {
            res.send('ok');
        } else {
            res.status(503).send();
        }
    });

    router.use('/dokus', addContextToRequest, apiRouter);

    return router;
};
