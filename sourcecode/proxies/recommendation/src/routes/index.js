import express from 'express';
import addContextToRequest from '../middlewares/addContextToRequest.js';
import swaggerJSDoc from 'swagger-jsdoc';
import swaggerUi from 'swagger-ui-express';
import recommendations from './recommendations.js';
import status from './status.js';

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
                    basePath: '/api/v1',
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
            message:
                'Welcome to the Edlib API. This is the recommendation module.',
        });
    });

    apiRouter.use(await recommendations());
    apiRouter.use(await status());

    router.get('/recommendations/_ah/health', (req, res) => {
        const probe = req.query.probe;
        if (probe === 'liveness') {
            res.send('ok');
        } else if (probe === 'readiness') {
            res.send('ok');
        } else {
            res.status(503).send();
        }
    });

    router.use('/recommendations', addContextToRequest, apiRouter);

    return router;
};