import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';

interface Video {
    id: number;
    title: string;
    thumbnail: string;
    videoUrl: string;
    summary_short?: string;
    duration_timestamp?: string;
}

interface ChannelGroup {
    id: number;
    name: string;
    url: string;
    thumbnail: string;
    videos: Video[];
}

interface BatchGroup {
    time: string;
    channels: ChannelGroup[];
    summaryMetrics?: {
        total_videos: number;
        total_duration: string;
        read_time: string;
        time_saved: string;
    };
    downloads?: {
        id: number;
        pdf: string | null;
        audio: string | null;
        pdf_status: 'pending' | 'processing' | 'completed' | 'failed';
        audio_status: 'pending' | 'processing' | 'completed' | 'failed';
        audio_duration?: number;
    };
}

interface DigestGroup {
    date: string;
    batches: BatchGroup[];
}

interface Props {
    auth: any;
    digests: DigestGroup[];
}

export default function Digest({ auth, digests }: Props) {
    const totalVideos = digests.reduce((acc, dateGroup) => {
        return acc + dateGroup.batches.reduce((bAcc, batch) => {
            return bAcc + batch.channels.reduce((cAcc, channel) => cAcc + channel.videos.length, 0);
        }, 0);
    }, 0);

    const totalChannels = digests.reduce((acc, dateGroup) => {
        return acc + dateGroup.batches.reduce((bAcc, batch) => bAcc + batch.channels.length, 0);
    }, 0);

    const [collapsedChannels, setCollapsedChannels] = useState<Set<string>>(new Set());

    const toggleChannel = (date: string, time: string, channelId: number) => {
        const key = `${date}-${time}-${channelId}`;
        setCollapsedChannels((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(key)) {
                newSet.delete(key);
            } else {
                newSet.add(key);
            }
            return newSet;
        });
    };

    const expandAll = () => {
        setCollapsedChannels(new Set());
    };

    const collapseAll = () => {
        const allKeys = digests.flatMap((dateGroup) =>
            dateGroup.batches.flatMap((batch) =>
                dateGroup.date + '-' + batch.time // Use simpler unique key logic or same as before
            )
        );
        // Fix collapse logic slightly or keep as is? User didn't complain about collapse.
        // Re-using existing valid content to be safe.
        const keys = digests.flatMap((dateGroup) =>
            dateGroup.batches.flatMap((batch) =>
                batch.channels.map((channel) => `${dateGroup.date}-${batch.time}-${channel.id}`)
            )
        );
        setCollapsedChannels(new Set(keys));
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
                // Reload to get duration
                router.reload({ only: ['digests'] });
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

    const handleDownloadClick = async (runId: number | undefined, type: 'pdf' | 'audio', key: string) => {
        if (!runId || downloading[key]) return;

        setDownloading(prev => ({ ...prev, [key]: true }));

        try {
            // Check status first
            const statusRes = await axios.get(route('digest_runs.status', runId));
            const status = type === 'pdf' ? statusRes.data.pdf_status : statusRes.data.audio_status;

            if (status === 'completed') {
                const url = type === 'pdf' ? statusRes.data.pdf_url : statusRes.data.audio_url;
                window.location.href = url;
                setDownloading(prev => ({ ...prev, [key]: false }));
            } else {
                // Trigger generation if not ready
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
        // Check for any ongoing "processing" status from props and resume polling
        const newDownloading: Record<string, boolean> = {};

        digests.forEach(dateGroup => {
            dateGroup.batches.forEach(batch => {
                if (batch.downloads) {
                    const { id, pdf_status, audio_status } = batch.downloads;

                    if (pdf_status === 'processing') {
                        const key = `${dateGroup.date}-${batch.time}-pdf`;
                        newDownloading[key] = true;
                        startPolling(id, 'pdf', key);
                    }

                    if (audio_status === 'processing') {
                        const key = `${dateGroup.date}-${batch.time}-audio`;
                        newDownloading[key] = true;
                        startPolling(id, 'audio', key);
                    }
                }
            });
        });

        if (Object.keys(newDownloading).length > 0) {
            setDownloading(prev => ({ ...prev, ...newDownloading }));
        }

        return () => {
            // eslint-disable-next-line react-hooks/exhaustive-deps
            Object.values(pollingRefs.current).forEach(clearInterval);
        };
    }, []);



    return (
        <AuthenticatedLayout
            // @ts-ignore
            user={auth.user}
        >
            <Head title="Daily Digests" />

            {/* Hero Header */}
            <div className="relative bg-gradient-to-br from-amber-50 via-orange-50 to-red-50 dark:from-amber-600 dark:via-orange-600 dark:to-red-700 overflow-hidden">
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-orange-500/5 dark:bg-white/5 blur-3xl" />
                    <div className="absolute -bottom-32 -left-32 w-80 h-80 rounded-full bg-red-500/5 dark:bg-yellow-400/10 blur-3xl" />
                </div>

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                        <div>
                            <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white tracking-tight">
                                Daily Digests
                            </h1>
                            <p className="mt-2 text-orange-800 dark:text-orange-100 text-lg">
                                AI-powered summaries of your subscribed channels' latest videos.
                            </p>
                        </div>

                        {digests.length > 0 && (
                            <div className="flex items-center gap-3">
                                <button
                                    onClick={expandAll}
                                    className="flex items-center gap-1.5 bg-white/50 hover:bg-white/80 dark:bg-white/10 dark:hover:bg-white/20 backdrop-blur-sm border border-orange-200/50 dark:border-white/10 text-orange-900 dark:text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-all shadow-sm"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                    </svg>
                                    Expand
                                </button>
                                <button
                                    onClick={collapseAll}
                                    className="flex items-center gap-1.5 bg-white/50 hover:bg-white/80 dark:bg-white/10 dark:hover:bg-white/20 backdrop-blur-sm border border-orange-200/50 dark:border-white/10 text-orange-900 dark:text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-all shadow-sm"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                                    </svg>
                                    Collapse
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Stats */}
                    {digests.length > 0 && (
                        <div className="mt-6 flex items-center gap-6">
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full bg-yellow-300" />
                                <span className="text-sm text-orange-800 dark:text-orange-200">
                                    <span className="font-semibold text-gray-900 dark:text-white">{totalVideos}</span> videos
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full bg-orange-300" />
                                <span className="text-sm text-orange-800 dark:text-orange-200">
                                    <span className="font-semibold text-gray-900 dark:text-white">{totalChannels}</span> channels
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full bg-red-300" />
                                <span className="text-sm text-orange-800 dark:text-orange-200">
                                    <span className="font-semibold text-gray-900 dark:text-white">{digests.length}</span> {digests.length === 1 ? 'day' : 'days'}
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                {digests.length === 0 ? (
                    /* Empty State */
                    <div className="rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-orange-50 dark:bg-orange-900/20">
                            <svg className="h-8 w-8 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z" />
                            </svg>
                        </div>
                        <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">No digests yet</h3>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400 max-w-sm mx-auto">
                            Subscribe to channels and wait for the next scheduled run to see your digests here.
                        </p>
                        <Link
                            href={route('youtube.subscriptions')}
                            className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-orange-600 hover:text-orange-500 dark:text-orange-400 transition-colors"
                        >
                            Manage Subscriptions
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-12">
                        {digests.map((dateGroup) => (
                            <div key={dateGroup.date}>
                                {/* Date header */}
                                <div className="flex items-center gap-4 mb-8">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-red-600 shadow-sm">
                                            <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                            </svg>
                                        </div>
                                        <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100">{dateGroup.date}</h3>
                                    </div>
                                    <div className="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
                                </div>

                                <div className="space-y-8">
                                    {dateGroup.batches.map((batch) => (
                                        <div key={batch.time}>
                                            {/* Batch time badge */}
                                            <div className="flex items-center gap-3 mb-5">
                                                <span className="inline-flex items-center gap-1.5 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 text-xs font-semibold px-3 py-1.5 rounded-lg ring-1 ring-inset ring-indigo-200 dark:ring-indigo-800">
                                                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {batch.time}
                                                </span>
                                                <span className="text-sm text-gray-600 dark:text-gray-400">
                                                    {batch.channels.reduce((acc, ch) => acc + ch.videos.length, 0)} videos across {batch.channels.length} {batch.channels.length === 1 ? 'channel' : 'channels'}
                                                </span>
                                            </div>

                                            {/* Batch Metrics */}
                                            {batch.summaryMetrics && (
                                                <div className="flex flex-wrap items-center gap-3 mb-6 ml-1">
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 text-xs font-medium border border-indigo-100 dark:border-indigo-800/30">
                                                        <span className="opacity-70">Watch:</span> {batch.summaryMetrics.total_duration}
                                                    </span>
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 text-xs font-medium border border-amber-100 dark:border-amber-800/30">
                                                        <span className="opacity-70">Read:</span> {batch.summaryMetrics.read_time}
                                                    </span>
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 text-xs font-medium border border-emerald-100 dark:border-emerald-800/30">
                                                        <span className="opacity-70">Saved:</span> {batch.summaryMetrics.time_saved}
                                                    </span>
                                                </div>
                                            )}

                                            {/* Downloads */}
                                            {batch.downloads && (
                                                <div className="flex flex-wrap items-center gap-3 mb-6 ml-1">
                                                    <button
                                                        onClick={() => batch.downloads?.id && handleDownloadClick(batch.downloads.id, 'pdf', `${dateGroup.date}-${batch.time}-pdf`)}
                                                        disabled={downloading[`${dateGroup.date}-${batch.time}-pdf`]}
                                                        className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-medium border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors ${downloading[`${dateGroup.date}-${batch.time}-pdf`] ? 'opacity-75 cursor-not-allowed' : ''}`}
                                                    >
                                                        {downloading[`${dateGroup.date}-${batch.time}-pdf`] ? (
                                                            <svg className="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                            </svg>
                                                        ) : (
                                                            <svg className="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                            </svg>
                                                        )}
                                                        {downloading[`${dateGroup.date}-${batch.time}-pdf`] ? 'Generating...' : 'Download PDF'}
                                                    </button>

                                                    <button
                                                        onClick={() => batch.downloads?.id && handleDownloadClick(batch.downloads.id, 'audio', `${dateGroup.date}-${batch.time}-audio`)}
                                                        disabled={downloading[`${dateGroup.date}-${batch.time}-audio`]}
                                                        className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-medium border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors ${downloading[`${dateGroup.date}-${batch.time}-audio`] ? 'opacity-75 cursor-not-allowed' : ''}`}
                                                    >
                                                        {downloading[`${dateGroup.date}-${batch.time}-audio`] ? (
                                                            <svg className="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                            </svg>
                                                        ) : (
                                                            <svg className="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                                <path strokeLinecap="round" strokeLinejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />
                                                            </svg>
                                                        )}
                                                        {downloading[`${dateGroup.date}-${batch.time}-audio`] ? 'Generating...' :
                                                            (batch.downloads.audio_duration
                                                                ? `Audio (${Math.floor(batch.downloads.audio_duration / 60)}:${(batch.downloads.audio_duration % 60).toString().padStart(2, '0')})`
                                                                : 'Download Audio')
                                                        }
                                                    </button>
                                                </div>
                                            )}
                                            <div className="space-y-5">
                                                {batch.channels.map((channel) => {
                                                    const channelKey = `${dateGroup.date}-${batch.time}-${channel.id}`;
                                                    const isCollapsed = collapsedChannels.has(channelKey);

                                                    return (
                                                        <div key={channel.id} className="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                                                            {/* Channel header */}
                                                            <button
                                                                onClick={() => toggleChannel(dateGroup.date, batch.time, channel.id)}
                                                                className="w-full flex items-center gap-4 px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                                            >
                                                                <svg
                                                                    className={`w-4 h-4 text-gray-400 transition-transform flex-shrink-0 ${isCollapsed ? '' : 'rotate-90'}`}
                                                                    fill="none"
                                                                    viewBox="0 0 24 24"
                                                                    stroke="currentColor"
                                                                    strokeWidth={2}
                                                                >
                                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                                </svg>
                                                                <div className="w-10 h-10 rounded-full overflow-hidden ring-2 ring-gray-100 dark:ring-gray-600 flex-shrink-0">
                                                                    {channel.thumbnail ? (
                                                                        <img src={channel.thumbnail} alt={channel.name} className="w-full h-full object-cover" />
                                                                    ) : (
                                                                        <div className="w-full h-full bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center text-white font-bold text-sm">
                                                                            {channel.name.substring(0, 1)}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                                <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100 flex-1 text-left truncate">
                                                                    {channel.name}
                                                                </h4>
                                                                <span className="text-xs font-medium text-gray-700 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-lg flex-shrink-0">
                                                                    {channel.videos.length} {channel.videos.length === 1 ? 'video' : 'videos'}
                                                                </span>
                                                            </button>

                                                            {/* Videos grid */}
                                                            {!isCollapsed && (
                                                                <div className="border-t border-gray-100 dark:border-gray-700 p-5">
                                                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                                                        {channel.videos.map((video) => (
                                                                            <div key={video.id} className="group/card rounded-xl border border-gray-300 dark:border-gray-700 overflow-hidden hover:border-indigo-200 dark:hover:border-indigo-800 hover:shadow-md transition-all duration-300 flex flex-col">
                                                                                <a href={video.videoUrl} target="_blank" rel="noopener noreferrer" className="block relative aspect-video overflow-hidden">
                                                                                    <img
                                                                                        src={video.thumbnail}
                                                                                        alt={video.title}
                                                                                        className="w-full h-full object-cover group-hover/card:scale-105 transition-transform duration-500"
                                                                                    />
                                                                                    <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover/card:opacity-100 transition-opacity bg-black/30 backdrop-blur-[2px]">
                                                                                        <div className="flex h-11 w-11 items-center justify-center rounded-full bg-white/90 shadow-lg">
                                                                                            <svg className="w-5 h-5 text-gray-800 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                                                                <path d="M8 5v14l11-7z" />
                                                                                            </svg>
                                                                                        </div>
                                                                                    </div>
                                                                                    {video.duration_timestamp && (
                                                                                        <div className="absolute bottom-2 right-2 z-10 bg-black/80 text-white text-xs font-bold px-2 py-1 rounded-[4px] pointer-events-none">
                                                                                            {video.duration_timestamp}
                                                                                        </div>
                                                                                    )}
                                                                                </a>
                                                                                <div className="p-3.5 flex flex-col flex-1">
                                                                                    <div className="flex-1">
                                                                                        <h5 className="font-medium text-sm text-gray-900 dark:text-gray-100 line-clamp-2 leading-snug mb-3" title={video.title}>
                                                                                            <a href={video.videoUrl} target="_blank" rel="noopener noreferrer" className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                                                                {video.title}
                                                                                            </a>
                                                                                        </h5>
                                                                                    </div>
                                                                                    <div className="mt-auto pt-3">
                                                                                        <Link
                                                                                            href={route('youtube.show', video.id)}
                                                                                            className="flex items-center justify-center gap-1.5 w-full px-3 py-2 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 rounded-lg text-xs font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-900/40 ring-1 ring-inset ring-indigo-200/50 dark:ring-indigo-800/50 transition-colors"
                                                                                        >
                                                                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                                                                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                                                            </svg>
                                                                                            View Summary
                                                                                        </Link>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
