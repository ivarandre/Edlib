import React from 'react';
import {
    Breadcrumbs,
    Box,
    Container,
    Grid,
    Typography,
    Paper,
    Table,
    TableHead,
    TableRow,
    TableCell,
    TableBody,
    Button,
} from '@material-ui/core';
import Link from '../../../../../../components/Link.jsx';
import CreateExternalApplication from './CreateExternalApplication.jsx';

const Home = ({ onGoToDetails }) => {
    const [createNew, setCreateNew] = React.useState(false);
    const apps = [
        {
            id: 1,
            name: 'Edstep',
        },
    ];

    return (
        <Container maxWidth={false}>
            <Grid component={Box} container paddingY={2}>
                <Grid item>
                    <Breadcrumbs aria-label="breadcrumb">
                        <Link to="/">Edlib admin</Link>
                        <Link to="/settings">Innstillinger</Link>
                        <Typography color="textPrimary">
                            Eksterne applikasjoner
                        </Typography>
                    </Breadcrumbs>
                </Grid>
            </Grid>
            <Grid container component={Box} paddingBottom={2}>
                <Grid item md={12}>
                    <Typography variant="h2">Eksterne applikasjoner</Typography>
                </Grid>
            </Grid>
            <Grid container>
                <Grid item md={12}>
                    <Paper>
                        <Box padding={2}>
                            <Button
                                variant="contained"
                                color="primary"
                                onClick={() => setCreateNew(true)}
                            >
                                Lag ny
                            </Button>
                        </Box>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Id</TableCell>
                                    <TableCell>Navn</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {apps.map(({ id, name }) => (
                                    <TableRow
                                        key={id}
                                        hover
                                        onClick={() => onGoToDetails(id)}
                                    >
                                        <TableCell>{id}</TableCell>
                                        <TableCell>{name}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Paper>
                </Grid>
            </Grid>
            <CreateExternalApplication
                isOpen={createNew}
                onClose={() => setCreateNew(false)}
            />
        </Container>
    );
};

export default Home;