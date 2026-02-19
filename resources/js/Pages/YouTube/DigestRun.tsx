import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import axios from 'axios';

interface Video {
    id: number;
    title: string;
    thumbnail: string;
    videoUrl: string;
    summary?: string;
    summary_detailed?: string;
    duration_timestamp?: string;
    published_at?: string;
}

interface ChannelGroup {
    id: number;
    name: string;
    url: string;
    thumbnail: string;
    videos: Video[];
}

interface Props {
    auth: any;
    digestDate: string;
    digestTime: string;
    channels: ChannelGroup[];
    shareToken: string;
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

export default function DigestRun({ auth, digestDate, digestTime, channels, shareToken, summaryMetrics, downloads }: Props) {
    const totalVideos = summaryMetrics?.total_videos ?? channels.reduce((acc, channel) => acc + channel.videos.length, 0);

    const [collapsedChannels, setCollapsedChannels] = useState<Set<number>>(new Set());

    const toggleChannel = (channelId: number) => {
        setCollapsedChannels((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(channelId)) {
                newSet.delete(channelId);
            } else {
                newSet.add(channelId);
            }
            return newSet;
        });
    };

    const expandAll = () => setCollapsedChannels(new Set());
    const collapseAll = () => setCollapsedChannels(new Set(channels.map(c => c.id)));

    // Download Logic
    const [downloading, setDownloading] = useState<{ pdf: boolean; audio: boolean }>({ pdf: false, audio: false });
    const pollingRefs = useRef<{ pdf?: NodeJS.Timeout; audio?: NodeJS.Timeout }>({});

    const checkStatus = async (runId: number, type: 'pdf' | 'audio') => {
        try {
            const response = await axios.get(route('digest_runs.status', runId));
            const status = type === 'pdf' ? response.data.pdf_status : response.data.audio_status;
            const url = type === 'pdf' ? response.data.pdf_url : response.data.audio_url;

            if (status === 'completed' && url) {
                if (pollingRefs.current[type]) {
                    clearInterval(pollingRefs.current[type]);
                    delete pollingRefs.current[type];
                }
                setDownloading(prev => ({ ...prev, [type]: false }));
                window.location.href = url;
                // Reload to update UI state if needed
                router.reload({ only: ['downloads'] });
            } else if (status === 'failed') {
                if (pollingRefs.current[type]) {
                    clearInterval(pollingRefs.current[type]);
                    delete pollingRefs.current[type];
                }
                setDownloading(prev => ({ ...prev, [type]: false }));
                alert(`Generation of ${type.toUpperCase()} failed.`);
            }
        } catch (error) {
            console.error('Status check failed:', error);
        }
    };

    const startPolling = (runId: number, type: 'pdf' | 'audio') => {
        if (pollingRefs.current[type]) return;
        pollingRefs.current[type] = setInterval(() => {
            checkStatus(runId, type);
        }, 3000);
    };

    const handleDownloadClick = async (type: 'pdf' | 'audio') => {
        if (!downloads?.id || downloading[type]) return;

        setDownloading(prev => ({ ...prev, [type]: true }));

        try {
            // Check status first
            const statusRes = await axios.get(route('digest_runs.status', downloads.id));
            const status = type === 'pdf' ? statusRes.data.pdf_status : statusRes.data.audio_status;

            if (status === 'completed') {
                const url = type === 'pdf' ? statusRes.data.pdf_url : statusRes.data.audio_url;
                if (url) {
                    window.location.href = url;
                    setDownloading(prev => ({ ...prev, [type]: false }));
                }
            } else {
                // Trigger generation if not ready
                const triggerUrl = type === 'pdf' ? route('digest_runs.pdf', downloads.id) : route('digest_runs.audio', downloads.id);
                await axios.get(triggerUrl);
                startPolling(downloads.id, type);
            }
        } catch (error) {
            console.error("Download trigger failed", error);
            setDownloading(prev => ({ ...prev, [type]: false }));
        }
    };

    useEffect(() => {
        if (downloads) {
            if (downloads.pdf_status === 'processing') {
                setDownloading(prev => ({ ...prev, pdf: true }));
                startPolling(downloads.id, 'pdf');
            }
            if (downloads.audio_status === 'processing') {
                setDownloading(prev => ({ ...prev, audio: true }));
                startPolling(downloads.id, 'audio');
            }
        }

        return () => {
            // eslint-disable-next-line react-hooks/exhaustive-deps
            if (pollingRefs.current.pdf) clearInterval(pollingRefs.current.pdf);
            // eslint-disable-next-line react-hooks/exhaustive-deps
            if (pollingRefs.current.audio) clearInterval(pollingRefs.current.audio);
        };
    }, []);

    return (
        <AuthenticatedLayout>
            <Head title={`Digest - ${digestDate} `} />

            {/* Hero */}
            <div className="relative bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 overflow-hidden">
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute top-10 left-20 w-72 h-72 bg-gray-50 dark:bg-gray-800/50 rounded-full blur-3xl opacity-60" />
                    <div className="absolute bottom-0 right-10 w-96 h-96 bg-gray-50 dark:bg-gray-800/50 rounded-full blur-3xl opacity-60" />
                </div>

                <div className="relative py-10 lg:py-14">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <div className="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 dark:bg-gray-800 dark:border-gray-700 px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 mb-3">
                                    <svg className="w-3 h-3 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Daily Digest
                                </div>
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-1">{digestDate}</h1>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Run at {digestTime}</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                {downloads && (
                                    <>
                                        <button
                                            onClick={() => handleDownloadClick('pdf')}
                                            disabled={downloading.pdf}
                                            className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold border border-gray-200 dark:border-gray-700 transition-all shadow-sm ${downloading.pdf ? 'opacity-75 cursor-not-allowed' : ''}`}
                                        >
                                            {downloading.pdf ? (
                                                <svg className="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            ) : (
                                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                </svg>
                                            )}
                                            {downloading.pdf ? 'Generating...' : 'PDF'}
                                        </button>

                                        <button
                                            onClick={() => handleDownloadClick('audio')}
                                            disabled={downloading.audio}
                                            className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold border border-gray-200 dark:border-gray-700 transition-all shadow-sm ${downloading.audio ? 'opacity-75 cursor-not-allowed' : ''}`}
                                        >
                                            {downloading.audio ? (
                                                <svg className="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            ) : (
                                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />
                                                </svg>
                                            )}
                                            {downloading.audio ? 'Generating...' :
                                                (downloads.audio_duration
                                                    ? `Audio (${Math.floor(downloads.audio_duration / 60)}:${(downloads.audio_duration % 60).toString().padStart(2, '0')})`
                                                    : 'Audio')
                                            }
                                        </button>

                                        <div className="h-4 w-px bg-gray-200 dark:bg-gray-700 mx-1" />
                                    </>
                                )}

                                {channels.length > 0 && (
                                    <div className="flex gap-2">
                                        <button
                                            onClick={expandAll}
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold border border-gray-200 dark:border-gray-700 transition-all shadow-sm"
                                        >
                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                            </svg>
                                            Expand
                                        </button>
                                        <button
                                            onClick={collapseAll}
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold border border-gray-200 dark:border-gray-700 transition-all shadow-sm"
                                        >
                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                                            </svg>
                                            Collapse
                                        </button>
                                    </div>
                                )}
                                <Link
                                    href={route('youtube.digest')}
                                    className="flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-lg px-4 py-1.5 ring-1 ring-gray-200 dark:ring-gray-700 transition-all"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    All Digests
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Summary Metrics Section */}
            <div className="bg-white border-b border-gray-200 dark:bg-gray-900 dark:border-gray-800">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div className="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700">
                            <div className="text-sm text-gray-500 dark:text-gray-400 font-medium mb-1">Total Videos</div>
                            <div className="text-2xl font-bold text-gray-900 dark:text-white">
                                {summaryMetrics?.total_videos ?? channels.reduce((acc, c) => acc + c.videos.length, 0)}
                            </div>
                        </div>
                        <div className="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-4 border border-indigo-100 dark:border-indigo-800/50">
                            <div className="text-sm text-indigo-600 dark:text-indigo-300 font-medium mb-1">Watch Time</div>
                            <div className="text-2xl font-bold text-indigo-700 dark:text-indigo-200">
                                {summaryMetrics?.total_duration ?? '0s'}
                            </div>
                        </div>
                        <div className="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 border border-amber-100 dark:border-amber-800/50">
                            <div className="text-sm text-amber-600 dark:text-amber-300 font-medium mb-1">Read Time</div>
                            <div className="text-2xl font-bold text-amber-700 dark:text-amber-200">
                                {summaryMetrics?.read_time ?? '0s'}
                            </div>
                        </div>
                        <div className="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-4 border border-emerald-100 dark:border-emerald-800/50">
                            <div className="text-sm text-emerald-600 dark:text-emerald-300 font-medium mb-1">Time Saved</div>
                            <div className="text-2xl font-bold text-emerald-700 dark:text-emerald-200">
                                {summaryMetrics?.time_saved ?? '0s'}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Content */}
            <div className="py-10 bg-gray-50 dark:bg-gray-900">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="space-y-12">
                        {channels.map((channel) => {
                            const isCollapsed = collapsedChannels.has(channel.id);

                            return (
                                <div key={channel.id} className={`rounded-xl border transition-all duration-200 overflow-hidden ${isCollapsed
                                    ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-700 shadow-sm'
                                    : 'bg-indigo-50/30 dark:bg-indigo-900/10 border-indigo-200 dark:border-indigo-800/30 ring-1 ring-indigo-500/10 dark:ring-indigo-400/10'
                                    }`}>
                                    <button
                                        onClick={() => toggleChannel(channel.id)}
                                        className="w-full flex items-center gap-4 px-4 py-3 text-left group transition-colors"
                                    >
                                        <div className="relative">
                                            {channel.thumbnail ? (
                                                <img src={channel.thumbnail} alt={channel.name} className="w-10 h-10 rounded-full ring-2 ring-white dark:ring-gray-700 shadow-sm" />
                                            ) : (
                                                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm shadow-sm">
                                                    {channel.name.substring(0, 1)}
                                                </div>
                                            )}
                                            <div className={`absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-white dark:bg-gray-800 shadow-sm flex items-center justify-center border border-gray-100 dark:border-gray-700`}>
                                                <div className={`w-2 h-2 rounded-full ${isCollapsed ? 'bg-gray-300 dark:bg-gray-600' : 'bg-green-500'}`} />
                                            </div>
                                        </div>

                                        <div className="flex-1 min-w-0">
                                            <h4 className={`text-lg font-black transition-colors tracking-tight truncate ${isCollapsed
                                                ? 'text-gray-700 dark:text-gray-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400'
                                                : 'text-indigo-900 dark:text-indigo-100'
                                                }`}>
                                                {channel.name}
                                            </h4>
                                            <p className="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                                <span>{channel.videos.length} {channel.videos.length === 1 ? 'video' : 'videos'} analyzed</span>
                                                {!isCollapsed && <span className="w-1 h-1 rounded-full bg-indigo-400" />}
                                                {!isCollapsed && <span className="text-indigo-500 dark:text-indigo-400">Expanded</span>}
                                            </p>
                                        </div>

                                        {/* Chevron */}
                                        <div className={`p-2 rounded-full transition-all duration-300 ${isCollapsed
                                            ? 'bg-gray-100 dark:bg-gray-700 text-gray-500 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600'
                                            : 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 rotate-180'
                                            }`}>
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </button>

                                    {!isCollapsed && (
                                        <div className="space-y-10 p-4 border-t border-indigo-100 dark:border-indigo-800/20">
                                            {channel.videos.map((video) => (
                                                <div key={video.id} className="bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-[2rem] border border-gray-200/50 dark:border-gray-700/50 overflow-hidden shadow-xl shadow-gray-200/20 dark:shadow-none">
                                                    {/* Video Header Section */}
                                                    <div className="p-6 sm:p-8 border-b border-gray-100 dark:border-gray-700/50 bg-white/30 dark:bg-gray-800/30">
                                                        <div className="flex flex-col md:flex-row gap-8 items-start">
                                                            <div className="w-full md:w-64 shrink-0">
                                                                <a href={video.videoUrl} target="_blank" rel="noopener noreferrer" className="relative group/thumb block aspect-video rounded-2xl overflow-hidden ring-1 ring-gray-200 dark:ring-white/10 shadow-lg">
                                                                    <img
                                                                        src={video.thumbnail}
                                                                        alt={video.title}
                                                                        className="w-full h-full object-cover transform group-hover/thumb:scale-110 transition-transform duration-1000"
                                                                    />
                                                                    <div className="absolute inset-0 bg-black/20 group-hover/thumb:bg-black/40 transition-colors flex items-center justify-center">
                                                                        <div className="w-10 h-10 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center ring-1 ring-white/30 transform group-hover/thumb:scale-110 transition-all duration-500">
                                                                            <svg className="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                                                <path d="M8 5v14l11-7z" />
                                                                            </svg>
                                                                        </div>
                                                                    </div>
                                                                    {video.duration_timestamp && (
                                                                        <div className="absolute bottom-2 right-2 bg-black/80 backdrop-blur-md text-white text-[9px] font-black px-1.5 py-0.5 rounded-md border border-white/10 tracking-widest">
                                                                            {video.duration_timestamp}
                                                                        </div>
                                                                    )}
                                                                </a>
                                                            </div>

                                                            <div className="flex-1 min-w-0">
                                                                <h5 className="text-xl sm:text-2xl font-black text-gray-900 dark:text-white leading-tight tracking-tight mb-4">
                                                                    <a href={video.videoUrl} target="_blank" rel="noopener noreferrer" className="hover:text-amber-500 dark:hover:text-amber-400 transition-colors">
                                                                        {video.title}
                                                                    </a>
                                                                </h5>
                                                                <div className="flex flex-wrap items-center gap-3">
                                                                    <span className="flex items-center gap-1.5 px-2 py-0.5 bg-amber-500/10 text-amber-500 rounded text-[9px] font-black uppercase tracking-widest border border-amber-500/20">
                                                                        <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                                                            <path d="M8 5v14l11-7z" />
                                                                        </svg>
                                                                        Video Analysis
                                                                    </span>
                                                                    <div className="h-1 w-1 rounded-full bg-gray-300 dark:bg-gray-700" />
                                                                    {video.published_at && (
                                                                        <span className="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">
                                                                            Released {video.published_at}
                                                                        </span>
                                                                    )}
                                                                    <div className="flex-1" />
                                                                    <Link
                                                                        href={route('youtube.show', video.id)}
                                                                        className="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 flex items-center gap-1.5"
                                                                    >
                                                                        Video Analysis
                                                                        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                                                        </svg>
                                                                    </Link>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Integrated Summary Section */}
                                                    <div className="px-6 pb-8 sm:px-8 sm:pb-10">
                                                        <div className="flex items-center gap-3 mb-6">
                                                            <div className="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-600 dark:text-amber-400">
                                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                </svg>
                                                            </div>
                                                            <h4 className="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-widest">AI Intelligence Digest</h4>
                                                        </div>

                                                        <div className="prose prose-sm md:prose-base dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 leading-relaxed">
                                                            {(video.summary || video.summary_detailed || "Summary analysis in progress...")
                                                                .split('\n')
                                                                .map((line, idx) => {
                                                                    if (line.startsWith('## ')) {
                                                                        return <h6 key={idx} className="text-gray-900 dark:text-white font-bold text-base mt-6 mb-4 uppercase tracking-wide">{line.replace('## ', '')}</h6>;
                                                                    }
                                                                    if (line.startsWith('- ')) {
                                                                        return <div key={idx} className="flex gap-4 mb-3 ps-4 text-sm md:text-base">
                                                                            <span className="text-amber-500 shrink-0 mt-2 h-2 w-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.5)]" />
                                                                            <span>{line.replace('- ', '')}</span>
                                                                        </div>;
                                                                    }
                                                                    return line.trim() ? <p key={idx} className="mb-4 text-sm md:text-base">{line}</p> : null;
                                                                })
                                                            }
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
