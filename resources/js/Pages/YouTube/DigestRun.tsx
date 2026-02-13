import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

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
}

export default function DigestRun({ auth, digestDate, digestTime, channels, shareToken, summaryMetrics }: Props) {
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

    return (
        <AuthenticatedLayout>
            <Head title={`Digest - ${digestDate}`} />

            {/* Hero */}
            <div className="relative overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-br from-amber-50 via-orange-50 to-red-50 dark:from-amber-950 dark:via-orange-950 dark:to-red-950" />
                <div className="absolute top-10 left-20 w-72 h-72 bg-amber-500/10 dark:bg-amber-500/20 rounded-full blur-3xl animate-pulse" />
                <div className="absolute bottom-0 right-10 w-96 h-96 bg-red-500/5 dark:bg-red-500/10 rounded-full blur-3xl" />

                <div className="relative py-10 lg:py-14">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <div className="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 backdrop-blur-sm px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-300 mb-3">
                                    <svg className="w-3 h-3 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Daily Digest
                                </div>
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-1">{digestDate}</h1>
                                <p className="text-sm text-gray-700 dark:text-amber-300/60">Run at {digestTime}</p>
                            </div>
                            <div className="flex items-center gap-3">
                                {channels.length > 0 && (
                                    <div className="flex gap-2">
                                        <button
                                            onClick={expandAll}
                                            className="text-xs px-3 py-1.5 bg-white/50 dark:bg-white/10 hover:bg-white/70 dark:hover:bg-white/20 text-orange-900 dark:text-white/80 rounded-lg ring-1 ring-orange-200/50 dark:ring-white/10 backdrop-blur-sm transition-all shadow-sm"
                                        >
                                            Expand All
                                        </button>
                                        <button
                                            onClick={collapseAll}
                                            className="text-xs px-3 py-1.5 bg-white/50 dark:bg-white/10 hover:bg-white/70 dark:hover:bg-white/20 text-orange-900 dark:text-white/80 rounded-lg ring-1 ring-orange-200/50 dark:ring-white/10 backdrop-blur-sm transition-all shadow-sm"
                                        >
                                            Collapse All
                                        </button>
                                    </div>
                                )}
                                <span className="text-sm text-amber-700 dark:text-amber-200 bg-white/50 dark:bg-white/10 backdrop-blur-sm px-3 py-1.5 rounded-lg ring-1 ring-gray-200 dark:ring-white/10 font-medium">
                                    {totalVideos} Videos
                                </span>
                                <Link
                                    href={route('youtube.digest')}
                                    className="flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-white/70 hover:text-gray-900 dark:hover:text-white bg-white/50 dark:bg-white/10 backdrop-blur-sm rounded-lg px-4 py-1.5 ring-1 ring-gray-200 dark:ring-white/10 hover:ring-gray-300 dark:hover:ring-white/20 transition-all"
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
                    <div className="space-y-6">
                        {channels.map((channel) => {
                            const isCollapsed = collapsedChannels.has(channel.id);

                            return (
                                <div key={channel.id} className="bg-white dark:bg-gray-800 rounded-2xl ring-1 ring-gray-200 dark:ring-gray-700/50 shadow-sm overflow-hidden">
                                    <button
                                        onClick={() => toggleChannel(channel.id)}
                                        className="w-full px-6 py-4 bg-gray-50/50 dark:bg-gray-700/30 flex items-center space-x-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer"
                                    >
                                        <svg
                                            className={`w-4 h-4 text-gray-400 transition-transform flex-shrink-0 ${isCollapsed ? '' : 'rotate-90'}`}
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                        {channel.thumbnail ? (
                                            <img src={channel.thumbnail} alt={channel.name} className="w-9 h-9 rounded-full flex-shrink-0 ring-2 ring-white dark:ring-gray-600 shadow-sm" />
                                        ) : (
                                            <div className="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-xs flex-shrink-0 shadow-sm">
                                                {channel.name.substring(0, 1)}
                                            </div>
                                        )}
                                        <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100 flex-1 text-left">
                                            {channel.name}
                                        </h4>
                                        <span className="text-xs font-medium text-gray-700 dark:text-gray-400 bg-gray-100 dark:bg-gray-600 px-2.5 py-1 rounded-full flex-shrink-0">
                                            {channel.videos.length} Videos
                                        </span>
                                    </button>

                                    {!isCollapsed && (
                                        <div className="p-6 border-t border-gray-100 dark:border-gray-700 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                                            {channel.videos.map((video) => (
                                                <div key={video.id} className="group rounded-xl overflow-hidden ring-1 ring-gray-300 dark:ring-gray-700/50 bg-white dark:bg-gray-800 hover:shadow-lg hover:ring-indigo-500/20 transition-all duration-300 flex flex-col">
                                                    <a href={video.videoUrl} target="_blank" rel="noopener noreferrer" className="block relative aspect-video overflow-hidden">
                                                        <img src={video.thumbnail} alt={video.title} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                                                        <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40 backdrop-blur-[2px]">
                                                            <div className="w-12 h-12 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center ring-1 ring-white/30">
                                                                <svg className="w-6 h-6 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
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
                                                    <div className="p-4 flex-1 flex flex-col">
                                                        <h5 className="font-semibold text-gray-900 dark:text-gray-100 mb-2 line-clamp-2 leading-snug text-sm" title={video.title}>
                                                            <a href={video.videoUrl} target="_blank" rel="noopener noreferrer" className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                                {video.title}
                                                            </a>
                                                        </h5>
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
