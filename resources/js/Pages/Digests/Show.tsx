import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';

interface DigestRun {
    id: number;
    created_at: string;
    summary_count: number;
    total_duration: number;
    pdf_path: string | null;
    audio_path: string | null;
    completed_at: string | null;
    pdf_status: 'pending' | 'processing' | 'completed' | 'failed';
    audio_status: 'pending' | 'processing' | 'completed' | 'failed';
}

interface Digest {
    id: number;
    name: string;
    frequency: string;
    scheduled_at: string;
    day_of_week?: string;
    mode: string;
    search_term?: string;
    is_active: boolean;
}

interface Props extends PageProps {
    digest: Digest;
    runs: DigestRun[];
}

export default function Show({ auth, digest, runs }: Props) {
    const formatDuration = (seconds: number) => {
        if (seconds < 60) return `${seconds}s`;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        if (hours > 0) return `${hours}h ${minutes}m`;
        return `${minutes}m`;
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const [downloading, setDownloading] = useState<Record<string, boolean>>({});
    const pollingRefs = useRef<Record<string, NodeJS.Timeout>>({});

    const checkStatus = async (runId: number, type: 'pdf' | 'audio', key: string) => {
        try {
            const response = await axios.get(route('digest_runs.status', runId));
            const status = type === 'pdf' ? response.data.pdf_status : response.data.audio_status;
            const url = type === 'pdf' ? response.data.pdf_url : response.data.audio_url;

            if (status === 'completed' && url) {
                if (pollingRefs.current[key]) {
                    clearInterval(pollingRefs.current[key]);
                    delete pollingRefs.current[key];
                }
                setDownloading(prev => ({ ...prev, [key]: false }));
                window.location.href = url;
            } else if (status === 'failed') {
                if (pollingRefs.current[key]) {
                    clearInterval(pollingRefs.current[key]);
                    delete pollingRefs.current[key];
                }
                setDownloading(prev => ({ ...prev, [key]: false }));
                alert(`Generation of ${type.toUpperCase()} failed.`);
            }
        } catch (error) {
            console.error('Status check failed:', error);
        }
    };

    const startPolling = (runId: number, type: 'pdf' | 'audio', key: string) => {
        if (pollingRefs.current[key]) return;
        pollingRefs.current[key] = setInterval(() => {
            checkStatus(runId, type, key);
        }, 3000);
    };

    const handleDownloadClick = async (runId: number, type: 'pdf' | 'audio') => {
        const key = `${runId}-${type}`;
        if (downloading[key]) return;

        setDownloading(prev => ({ ...prev, [key]: true }));

        try {
            const statusRes = await axios.get(route('digest_runs.status', runId));
            const status = type === 'pdf' ? statusRes.data.pdf_status : statusRes.data.audio_status;

            if (status === 'completed') {
                const url = type === 'pdf' ? statusRes.data.pdf_url : statusRes.data.audio_url;
                window.location.href = url;
                setDownloading(prev => ({ ...prev, [key]: false }));
            } else {
                const triggerUrl = type === 'pdf' ? route('digest_runs.pdf', runId) : route('digest_runs.audio', runId);
                await axios.get(triggerUrl);
                startPolling(runId, type, key);
            }
        } catch (error) {
            console.error("Download trigger failed", error);
            setDownloading(prev => ({ ...prev, [key]: false }));
        }
    };

    useEffect(() => {
        return () => {
            // eslint-disable-next-line react-hooks/exhaustive-deps
            Object.values(pollingRefs.current).forEach(clearInterval);
        };
    }, []);

    return (
        <AuthenticatedLayout
            // @ts-ignore
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Digest History: {digest.name}</h2>}
        >
            <Head title={`Digest: ${digest.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <Link
                            href={route('digests.index')}
                            className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            &larr; Back to Digests
                        </Link>
                        <Link
                            href={route('digests.edit', digest.id)}
                            className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            Edit Settings
                        </Link>
                    </div>

                    <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            <h3 className="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 mb-4">Run History</h3>
                            {runs.length === 0 ? (
                                <p className="text-gray-500 dark:text-gray-400">No runs recorded yet.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead className="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Videos</th>
                                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duration</th>
                                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Downloads</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            {runs.map((run) => (
                                                <tr key={run.id}>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {formatDate(run.created_at)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        {run.summary_count}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {formatDuration(run.total_duration)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                        {run.completed_at ? (
                                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                                Completed
                                                            </span>
                                                        ) : (
                                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                                Processing
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                        <button
                                                            onClick={() => handleDownloadClick(run.id, 'pdf')}
                                                            disabled={downloading[`${run.id}-pdf`]}
                                                            className={`text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 ${downloading[`${run.id}-pdf`] ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                        >
                                                            {downloading[`${run.id}-pdf`] ? 'Generating PDF...' : 'PDF'}
                                                        </button>
                                                        <button
                                                            onClick={() => handleDownloadClick(run.id, 'audio')}
                                                            disabled={downloading[`${run.id}-audio`]}
                                                            className={`text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 ${downloading[`${run.id}-audio`] ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                        >
                                                            {downloading[`${run.id}-audio`] ? 'Generating Audio...' : 'Audio'}
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
