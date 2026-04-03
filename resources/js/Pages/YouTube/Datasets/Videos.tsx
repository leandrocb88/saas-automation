import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import TextInput from '@/Components/TextInput';
import Select from '@/Components/Select';
import Pagination from '@/Components/Pagination';
import { formatLocalDate } from '@/utils/date';

interface Video {
    id: number;
    video_id: string;
    title: string;
    channel_title: string;
    thumbnail_url: string;
    created_at: string;
    duration: number;
    published_at: string;
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

interface Props {
    auth: any;
    dataset: {
        id: number;
        name: string;
        channel_url: string;
    };
    videos: PaginatedVideos;
    filters: {
        search?: string;
        sort?: string;
        per_page?: string;
        date_from?: string;
        date_to?: string;
    };
}

export default function Videos({ auth, dataset, videos, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [sort, setSort] = useState(filters.sort || 'published_newest');

    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (search !== (filters.search || '') || sort !== (filters.sort || 'published_newest')) {
                router.get(route('datasets.videos', dataset.id), { ...filters, search, sort, page: 1 }, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true
                });
            }
        }, 500);

        return () => clearTimeout(delayDebounceFn);
    }, [search]); // Intentionally omitting sort from dependency array to prevent double-firing since onChange handles it

    const handleSortChange = (newSort: string) => {
        setSort(newSort);
        router.get(route('datasets.videos', dataset.id), { ...filters, sort: newSort, page: 1 }, {
            preserveState: true,
            preserveScroll: true
        });
    };

    const handlePerPageChange = (newPerPage: string) => {
        router.get(route('datasets.videos', dataset.id), { ...filters, per_page: newPerPage, page: 1 }, { preserveState: true, preserveScroll: true });
    };

    const sortOptions = [
        { value: 'published_newest', label: 'Published: Newest First' },
        { value: 'published_oldest', label: 'Published: Oldest First' },
        { value: 'newest', label: 'Added: Newest First' },
        { value: 'older', label: 'Added: Oldest First' },
        { value: 'alphabetical_asc', label: 'Alphabetical (A-Z)' },
        { value: 'alphabetical_desc', label: 'Alphabetical (Z-A)' },
    ];

    const perPageOptions = [
        { value: '24', label: '24' },
        { value: '48', label: '48' },
        { value: '60', label: '60' },
        { value: '100', label: '100' },
    ];

    return (
        <AuthenticatedLayout>
            <Head title={`Videos - ${dataset.name}`} />

            <div className="relative bg-gradient-to-br from-indigo-50 via-purple-50 to-indigo-100 dark:from-indigo-900 dark:via-purple-900 dark:to-indigo-900 overflow-hidden">
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-indigo-500/10 dark:bg-white/5 blur-3xl" />
                    <div className="absolute -bottom-32 -left-32 w-80 h-80 rounded-full bg-purple-500/10 dark:bg-purple-400/10 blur-3xl" />
                </div>

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                        <div>
                            <nav className="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-4 transition-all">
                                <Link href={route('datasets.index')} className="hover:text-indigo-800 dark:hover:text-indigo-200">Knowledge Base</Link>
                                <span>/</span>
                                <span className="text-gray-500 dark:text-gray-400">Dataset Videos</span>
                            </nav>
                            <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white tracking-tight">
                                {dataset.name}
                            </h1>
                            <p className="mt-2 text-indigo-900 dark:text-indigo-100 text-lg line-clamp-1 opacity-80">
                                Browse all videos processed for this dataset.
                            </p>
                        </div>
                        <div className="flex items-center gap-4">
                            <Link
                                href={route('datasets.index')}
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-white/50 dark:bg-white/10 backdrop-blur-sm border border-gray-200 dark:border-white/10 text-gray-700 dark:text-white px-6 py-3 text-sm font-semibold shadow-sm transition-all hover:bg-white dark:hover:bg-white/20"
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                                </svg>
                                Back to Dashboard
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                <div className="flex flex-col space-y-6 mb-10">
                    {/* Unified Action & Filter Bar */}
                    <div className="flex flex-col xl:flex-row items-stretch xl:items-center gap-4">
                        {/* Search - Flexible */}
                        <div className="relative flex-1 min-w-0 group">
                            <TextInput
                                type="text"
                                placeholder="Search in this dataset..."
                                className="block w-full pl-14 pr-4 py-3 bg-white/50 dark:bg-white/10 backdrop-blur-sm border-gray-200 dark:border-white/10 rounded-2xl text-[13px] focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/50 dark:text-white transition-all shadow-sm h-[52px]"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                            <div className="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none z-10">
                                <svg
                                    className="h-5 w-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </div>
                        </div>

                        {/* Secondary Actions Group */}
                        <div className="flex flex-wrap sm:flex-nowrap items-center gap-3">
                            <Select
                                label="Sort"
                                options={sortOptions}
                                value={sort}
                                onChange={handleSortChange}
                                minWidth="200px"
                                className="flex-1 sm:flex-none"
                            />

                            <Select
                                label="Show"
                                options={perPageOptions}
                                value={videos.per_page.toString()}
                                onChange={handlePerPageChange}
                                minWidth="120px"
                                className="flex-1 sm:flex-none"
                            />
                        </div>
                    </div>

                    {/* Stats Bar */}
                    {videos.total > 0 && (
                        <div className="flex items-center gap-8 px-2 transition-all animate-in fade-in slide-in-from-top-1 duration-500">
                            <div className="flex items-center gap-2.5">
                                <div className="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.8)]" />
                                <span className="text-[11px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-[0.2em] flex items-center gap-1.5">
                                    <span className="text-gray-900 dark:text-white text-[13px]">{videos.total}</span> videos found
                                </span>
                            </div>
                            <div className="flex items-center gap-2.5">
                                <svg className="w-4 h-4 text-gray-400 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span className="text-[11px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-[0.2em]">
                                    Showing {videos.from}–{videos.to}
                                </span>
                            </div>
                        </div>
                    )}
                </div>

                {videos.data.length === 0 ? (
                    <div className="rounded-[2.5rem] border-2 border-dashed border-gray-200 dark:border-gray-700 p-16 text-center bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm">
                        <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/20 mb-6">
                            <svg className="h-10 w-10 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                        <h3 className="text-xl font-bold text-gray-900 dark:text-white">No results found</h3>
                        <p className="mt-2 text-gray-500 dark:text-gray-400 max-w-xs mx-auto">
                            We couldn't find any videos in this dataset matching your search criteria.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-10">
                            {videos.data.map((video) => (
                                <div key={video.id} className="group relative bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm hover:shadow-2xl dark:hover:shadow-none hover:border-indigo-200 dark:hover:border-indigo-500/30 transition-all duration-300 overflow-hidden flex flex-col">
                                    <Link href={route('youtube.show', video.id)} className="block relative aspect-video overflow-hidden">
                                        {video.thumbnail_url ? (
                                            <img
                                                src={video.thumbnail_url}
                                                alt={video.title}
                                                className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                                            />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center bg-gray-100 dark:bg-gray-900">
                                                <svg className="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
                                            </div>
                                        )}
                                        <div className="absolute inset-0 bg-black/10 group-hover:bg-black/50 transition-all flex items-center justify-center">
                                            <div className="transform scale-90 opacity-0 group-hover:scale-100 group-hover:opacity-100 transition-all duration-300 bg-white/20 backdrop-blur-md p-3 rounded-full outline outline-1 outline-white/30">
                                                <svg className="w-8 h-8 text-white fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
                                            </div>
                                        </div>
                                    </Link>

                                    <div className="p-6 flex flex-col flex-1">
                                        <h3 className="font-bold text-gray-900 dark:text-white leading-tight line-clamp-2 mb-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors tracking-tight">
                                            <Link href={route('youtube.show', video.id)}>{video.title}</Link>
                                        </h3>
                                        <div className="flex items-center gap-2 mb-4">
                                            <div className="w-1.5 h-1.5 rounded-full bg-indigo-500" />
                                            <p className="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 truncate">
                                                {video.channel_title}
                                            </p>
                                        </div>

                                        <div className="mt-auto flex flex-col gap-2 pt-4 border-t border-gray-50 dark:border-gray-700/50">
                                            {video.published_at && (
                                                <div className="flex items-center gap-1 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">
                                                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    Published: {formatLocalDate(video.published_at)}
                                                </div>
                                            )}
                                            <div className="flex items-center justify-between text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">
                                                <span>Processed: {formatLocalDate(video.created_at)}</span>
                                            </div>
                                            <Link
                                                href={route('youtube.show', video.id)}
                                                className="mt-1 flex items-center justify-center gap-2 w-full px-4 py-3 bg-gray-50 dark:bg-white/5 text-gray-900 dark:text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-600 transition-all duration-300 ring-1 ring-gray-200 dark:ring-white/10 shadow-sm"
                                            >
                                                Analysis
                                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <Pagination 
                            links={videos.links} 
                            meta={{
                                current_page: videos.current_page,
                                from: videos.from,
                                to: videos.to,
                                total: videos.total
                            }} 
                        />
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
