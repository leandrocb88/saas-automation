import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmationModal from '@/Components/ConfirmationModal';

interface Video {
    id: number;
    video_id: string;
    title: string;
    thumbnail: string | null;
    date: string;
    channel?: string;
}

interface PaginatedVideos {
    data: Video[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    total: number;
    per_page: number;
    current_page: number;
    from: number;
    to: number;
}

interface HistoryProps {
    auth: any;
    videos: PaginatedVideos;
    canViewHistory: boolean;
    retentionDays: number;
    queryParams?: any;
}

export default function History({ auth, videos, canViewHistory, retentionDays, queryParams = null }: HistoryProps) {
    queryParams = queryParams || {};
    const [confirmingClearHistory, setConfirmingClearHistory] = useState(false);
    const [videoToDelete, setVideoToDelete] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);

    const confirmClearHistory = () => {
        setConfirmingClearHistory(true);
    };

    const clearHistory = () => {
        setProcessing(true);
        router.delete(route('youtube.clear'), {
            onFinish: () => {
                setProcessing(false);
                setConfirmingClearHistory(false);
            },
        });
    };

    const confirmDeleteVideo = (id: number) => {
        setVideoToDelete(id);
    };

    const deleteVideo = () => {
        if (!videoToDelete) return;
        setProcessing(true);
        router.delete(route('youtube.destroy', videoToDelete), {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setVideoToDelete(null);
            },
        });
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = e.target.value;
        router.get(route('youtube.history'), { ...queryParams, per_page: newPerPage }, { preserveState: true });
    };

    const totalPages = Math.ceil(videos.total / videos.per_page);

    return (
        <AuthenticatedLayout>
            <Head title="History" />

            {/* Hero Header */}
            <div className="relative bg-gradient-to-br from-slate-800 via-slate-900 to-gray-900 overflow-hidden">
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-indigo-500/10 blur-3xl" />
                    <div className="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-purple-500/10 blur-3xl" />
                </div>

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                        <div>
                            <h1 className="text-3xl sm:text-4xl font-bold text-white tracking-tight">
                                History
                            </h1>
                            <p className="mt-2 text-gray-400 text-lg">
                                Your processed videos from the last {retentionDays} days.
                            </p>
                        </div>

                        {canViewHistory && videos.total > 0 && (
                            <div className="flex items-center gap-4">
                                <div className="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10">
                                    <label htmlFor="per_page" className="text-xs text-gray-400 whitespace-nowrap">Show</label>
                                    <select
                                        id="per_page"
                                        value={videos.per_page}
                                        onChange={handlePerPageChange}
                                        className="bg-transparent border-0 text-sm text-white focus:ring-0 py-0 pl-1 pr-6 cursor-pointer [&>option]:text-gray-900"
                                    >
                                        <option value="20">20</option>
                                        <option value="40">40</option>
                                        <option value="60">60</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>

                                <button
                                    onClick={confirmClearHistory}
                                    className="flex items-center gap-1.5 bg-red-500/10 hover:bg-red-500/20 backdrop-blur-sm text-red-400 hover:text-red-300 border border-red-500/20 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                    Clear All
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Stats bar */}
                    {canViewHistory && videos.total > 0 && (
                        <div className="mt-6 flex items-center gap-6">
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full bg-emerald-400" />
                                <span className="text-sm text-gray-300">
                                    <span className="font-semibold text-white">{videos.total}</span> videos total
                                </span>
                            </div>
                            <div className="text-sm text-gray-500">
                                Showing {videos.from}–{videos.to}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                {!canViewHistory ? (
                    /* Upgrade CTA */
                    <div className="rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/20">
                            <svg className="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                        </div>
                        <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">History Not Available</h3>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                            Upgrade to a paid plan to save and view your video processing history.
                        </p>
                        <Link
                            href={route('plans')}
                            className="mt-6 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors"
                        >
                            View Plans
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                ) : videos.data.length === 0 ? (
                    /* Empty State */
                    <div className="rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/20">
                            <svg className="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">No history yet</h3>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                            Process a video to see it appear here.
                        </p>
                        <Link
                            href={route('youtube.home')}
                            className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 transition-colors"
                        >
                            Process your first video
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                ) : (
                    <>
                        {/* Video Grid */}
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {videos.data.map((video) => (
                                <div key={video.id} className="group/card rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden hover:border-indigo-200 dark:hover:border-indigo-800 hover:shadow-md transition-all duration-300 bg-white dark:bg-gray-800 flex flex-col">
                                    <div className="relative aspect-video overflow-hidden">
                                        {/* Delete button */}
                                        <button
                                            onClick={(e) => {
                                                e.preventDefault();
                                                confirmDeleteVideo(video.id);
                                            }}
                                            className="absolute top-2.5 right-2.5 z-20 p-2 bg-black/40 backdrop-blur-sm hover:bg-red-600 text-white rounded-xl opacity-0 group-hover/card:opacity-100 transition-all duration-200"
                                            title="Delete Video"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-4 h-4">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>

                                        <Link href={route('youtube.show', video.id)} className="block w-full h-full">
                                            {video.thumbnail ? (
                                                <img
                                                    src={video.thumbnail}
                                                    alt={video.title}
                                                    className="w-full h-full object-cover group-hover/card:scale-105 transition-transform duration-500"
                                                />
                                            ) : (
                                                <div className="flex h-full w-full items-center justify-center bg-gray-100 dark:bg-gray-700">
                                                    <div className="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                                                        <svg className="w-7 h-7 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Play Overlay */}
                                            <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover/card:opacity-100 transition-opacity bg-black/30 backdrop-blur-[2px]">
                                                <div className="flex h-11 w-11 items-center justify-center rounded-full bg-white/90 shadow-lg">
                                                    <svg className="w-5 h-5 text-gray-800 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M8 5v14l11-7z" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </Link>
                                    </div>

                                    <div className="p-4 flex flex-col flex-1">
                                        <h3 className="font-medium text-sm text-gray-900 dark:text-gray-100 line-clamp-2 leading-snug mb-2" title={video.title}>
                                            <Link href={route('youtube.show', video.id)} className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                {video.title}
                                            </Link>
                                        </h3>

                                        {video.channel && (
                                            <p className="text-xs text-gray-500 dark:text-gray-400 truncate mb-3">
                                                {video.channel}
                                            </p>
                                        )}

                                        <div className="mt-auto pt-4 flex flex-col gap-3">
                                            <span className="text-xs text-gray-400">{video.date}</span>
                                            <Link
                                                href={route('youtube.show', video.id)}
                                                className="flex items-center justify-center gap-1.5 w-full px-3 py-2 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 rounded-lg text-xs font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-900/40 ring-1 ring-inset ring-indigo-200/50 dark:ring-indigo-800/50 transition-colors"
                                            >
                                                View Summary
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="mt-10 flex flex-col sm:flex-row justify-between items-center gap-4">
                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                    Page <span className="font-semibold text-gray-700 dark:text-gray-200">{videos.current_page}</span> of <span className="font-semibold text-gray-700 dark:text-gray-200">{totalPages}</span>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    {videos.links.map((link, key) => (
                                        <Link
                                            key={key}
                                            href={link.url || '#'}
                                            className={`px-3.5 py-2 rounded-xl text-sm font-medium transition-all ${link.active
                                                ? 'bg-indigo-600 text-white shadow-sm'
                                                : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300'
                                                } ${!link.url && 'opacity-40 cursor-not-allowed pointer-events-none'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            <ConfirmationModal
                show={confirmingClearHistory}
                title="Clear History"
                content="Are you sure you want to clear your entire history? This cannot be undone."
                confirmText="Clear History"
                onClose={() => setConfirmingClearHistory(false)}
                onConfirm={clearHistory}
                processing={processing}
            />

            <ConfirmationModal
                show={videoToDelete !== null}
                title="Delete Video"
                content="Are you sure you want to delete this video? This action cannot be undone."
                confirmText="Delete Video"
                onClose={() => setVideoToDelete(null)}
                onConfirm={deleteVideo}
                processing={processing}
            />
        </AuthenticatedLayout>
    );
}
