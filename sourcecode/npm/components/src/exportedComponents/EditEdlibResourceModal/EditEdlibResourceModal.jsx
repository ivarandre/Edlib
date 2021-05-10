import React from 'react';
import useFetchWithToken from '../../hooks/useFetchWithToken';
import useConfig from '../../hooks/useConfig';
import { Modal, Spinner, ThemeProvider, themes } from '@cerpus/ui';
import ResourceEditor from '../../components/ResourceEditor';
import { useEdlibResource } from '../../hooks/requests/useResource';
import ModalHeader from '../../components/ModalHeader';
import { MemoryRouter } from 'react-router-dom';

const EditEdlibResourceModal = ({ ltiLaunchUrl, onUpdateDone }) => {
    const { edlib } = useConfig();
    const createResourceLink = useEdlibResource();
    const { response, error, loading } = useFetchWithToken(
        edlib('/lti/v1/lti/convert-launch-url'),
        'GET',
        React.useMemo(
            () => ({
                query: {
                    launchUrl: ltiLaunchUrl,
                },
            }),
            [ltiLaunchUrl]
        )
    );

    if (loading || !response) {
        return <Spinner />;
    }

    if (error) {
        return <div>Noe skjedde</div>;
    }

    return (
        <ResourceEditor
            edlibId={response.edlibId}
            onResourceReturned={async (newEdlibId) => {
                if (newEdlibId === response.edlibId) {
                    return onUpdateDone(null);
                }

                const info = await createResourceLink(newEdlibId);

                onUpdateDone(info);
            }}
        />
    );
};

export default ({ removePadding = false, ...props }) => {
    return (
        <ThemeProvider materialUITheme={themes.edlib}>
        <Modal
            isOpen={true}
            width="100%"
            onClose={props.onClose}
            displayCloseButton={false}
            removePadding={removePadding}
        >
            <div
                style={{
                    height: removePadding ? '100vh' : 'calc(100vh - 40px)',
                    display: 'flex',
                    flexDirection: 'column',
                }}
            >
                <ModalHeader onClose={props.onClose}>
                    {props.header}
                </ModalHeader>
                <EditEdlibResourceModal {...props} />
            </div>
        </Modal>
        </ThemeProvider>
    );
};