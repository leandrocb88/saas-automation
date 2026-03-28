import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { formatLocalDateTime } from '@/utils/date';

interface DigestRun {
    id: number;
    created_at: string;
    summary_count: number;
    total_duration: number;
    batch_id: string;
    pdf_path: string | null;
    audio_path: string | null;
    completed_at: string | null;
    status: 'processing' | 'completed' | 'failed';
    pdf_status: 'pending' | 'processing' | 'completed' | 'failed';
    audio_status: 'pending' | 'processing' | 'completed' | 'failed';
    digest?: Digest;
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
    runs: {
        data: DigestRun[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    digestId?: string;
}

export default function Index({ auth, runs, digestId }: Props) {
    const formatDuration = (seconds: number) => {
        if (seconds < 60) return `${seconds}s`;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        if (hours > 0) return `${hours}h ${minutes}m`;
        return `${minutes}m`;
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
        <AuthenticatedLayout>
            <Head title="Digest History" />

            {/* Hero Header */}
            <div className="relative bg-gradient-to-br from-indigo-50 via-purple-50 to-indigo-100 dark:from-indigo-900 dark:via-purple-900 dark:to-indigo-900 overflow-hidden">
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-indigo-500/10 dark:bg-white/5 blur-3xl" />
                    <div className="absolute -bottom-32 -left-32 w-80 h-80 rounded-full bg-purple-500/10 dark:bg-purple-400/10 blur-3xl" />
                </div>

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                        <div>
                            <Link href={route('digests.index')} className="inline-flex items-center text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 mb-4 transition-colors">
                                <svg className="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Digests
                            </Link>

                            <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white tracking-tight">
                                Digest History {digestId && '(Filtered)'}
                            </h1>
                            <p className="mt-2 text-indigo-900 dark:text-indigo-100 text-lg max-w-xl">
                                Detailed history of your automated digest runs and their generated content.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                <div className="bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-[2rem] border border-gray-200/50 dark:border-gray-700/50 shadow-xl shadow-gray-200/20 dark:shadow-none overflow-hidden">
                    <div className="p-8">
                        <div className="flex items-center justify-between mb-8">
                            <h3 className="text-xl font-bold text-gray-900 dark:text-white">Run History</h3>
                            <div className="text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-4 py-1.5 rounded-full border border-gray-100 dark:border-gray-700 shadow-sm">
                                Total runs: {runs.total}
                            </div>
                        </div>

                        {runs.data.length === 0 ? (
                            <div className="rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center bg-gray-50/50 dark:bg-gray-800/30">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/20 shadow-inner">
                                    <svg className="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">No runs recorded yet</h3>
                                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                                    Runs will appear here automatically based on your digest schedules.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {runs.data.map((run) => (
                                    <div key={run.id} className="group flex flex-col sm:flex-row sm:items-center justify-between p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm hover:shadow-md transition-all duration-300 gap-4 sm:gap-6">
                                        
                                        {/* Run Info */}
                                        <div className="flex items-center gap-5 flex-1 min-w-0">
                                            <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/40 dark:to-purple-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 shadow-inner">
                                                <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-3 mb-1">
                                                    <h4 className="text-base font-bold text-gray-900 dark:text-white truncate">
                                                        {formatLocalDateTime(run.created_at)}
                                                    </h4>
                                                    {run.status === 'completed' ? (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                                                            Completed
                                                        </span>
                                                    ) : run.status === 'failed' ? (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-800 dark:bg-rose-500/10 dark:text-rose-400 border border-rose-200 dark:border-rose-500/20">
                                                            Failed
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">
                                                            <div className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
                                                            Processing
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                                    <div className="flex items-center text-indigo-600 dark:text-indigo-400 font-medium">
                                                        {run.digest ? run.digest.name : 'Deleted Digest'}
                                                    </div>
                                                    <div className="flex items-center">
                                                        <svg className="w-4 h-4 mr-1.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                        </svg>
                                                        {run.summary_count} {run.summary_count === 1 ? 'Video' : 'Videos'}
                                                    </div>
                                                    <div className="flex items-center">
                                                        <svg className="w-4 h-4 mr-1.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        {formatDuration(run.total_duration)} Total Content
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        {/* Actions */}
                                        <div className="flex sm:flex-col lg:flex-row items-center justify-end gap-2 shrink-0 border-t sm:border-t-0 sm:border-l border-gray-100 dark:border-gray-700/50 pt-4 sm:pt-0 sm:pl-6 mt-2 sm:mt-0 w-full sm:w-auto">
                                            {run.status === 'completed' ? (
                                                <Link
                                                    href={route('youtube.digest.show', run.batch_id)}
                                                    className="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 text-sm font-semibold rounded-xl hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-all border border-indigo-100 dark:border-indigo-500/20 active:scale-95"
                                                >
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    View Videos
                                                </Link>
                                            ) : run.status === 'failed' ? (
                                                <div className="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 text-sm font-semibold rounded-xl border border-rose-100 dark:border-rose-500/20 opacity-60">
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Failed
                                                </div>
                                            ) : (
                                                <div className="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-50 dark:bg-gray-800 text-gray-400 dark:text-gray-500 text-sm font-semibold rounded-xl border border-gray-100 dark:border-gray-700 cursor-not-allowed">
                                                    <svg className="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Processing...
                                                </div>
                                            )}
                                            <button
                                                onClick={() => handleDownloadClick(run.id, 'pdf')}
                                                disabled={downloading[`${run.id}-pdf`] || run.status !== 'completed'}
                                                className={`flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 bg-white/50 dark:bg-gray-800/50 hover:bg-white/80 dark:hover:bg-gray-700/80 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl transition-all border border-gray-200/50 dark:border-gray-700/50 active:scale-95 ${(downloading[`${run.id}-pdf`] || run.status !== 'completed') ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            >
                                                {downloading[`${run.id}-pdf`] ? (
                                                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                ) : (
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                )}
                                                {downloading[`${run.id}-pdf`] ? '...' : 'PDF'}
                                            </button>
                                            <button
                                                onClick={() => handleDownloadClick(run.id, 'audio')}
                                                disabled={downloading[`${run.id}-audio`] || run.status !== 'completed'}
                                                className={`flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 bg-white/50 dark:bg-gray-800/50 hover:bg-white/80 dark:hover:bg-gray-700/80 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl transition-all border border-gray-200/50 dark:border-gray-700/50 active:scale-95 ${(downloading[`${run.id}-audio`] || run.status !== 'completed') ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            >
                                                {downloading[`${run.id}-audio`] ? (
                                                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                ) : (
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                                    </svg>
                                                )}
                                                {downloading[`${run.id}-audio`] ? '...' : 'Audio'}
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {runs.links && runs.links.length > 3 && (
                            <div className="mt-8 flex justify-center pb-4">
                                <div className="inline-flex items-center justify-center space-x-1 bg-white dark:bg-gray-800 rounded-xl p-1.5 border border-gray-200 dark:border-gray-700/50 shadow-sm">
                                    {runs.links.map((link, idx) => (
                                        link.url ? (
                                            <Link
                                                key={idx}
                                                href={link.url}
                                                className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${link.active 
                                                    ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20' 
                                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                                                }`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ) : (
                                            <span 
                                                key={idx} 
                                                className="px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-500" 
                                                dangerouslySetInnerHTML={{ __html: link.label }} 
                                            />
                                        )
                                    ))}
                                </div>
                            </div>
                        )}

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
