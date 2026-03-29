import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react'; // Use router from @inertiajs/react
import { PageProps } from '@/types';
import ConfirmationModal from '@/Components/ConfirmationModal';
import Pagination from '@/Components/Pagination';
import { useState } from 'react';

interface Digest {
    id: number;
    name: string;
    frequency: string;
    scheduled_at: string;
    day_of_week?: string;
    mode: string;
    search_term?: string; // Add optional search_term
    is_active: boolean;
    channels_count: number;
    last_run_at?: string;
    last_time_change_at?: string;
}

interface Props extends PageProps {
    digests: {
        data: Digest[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
        current_page: number;
        from: number;
        to: number;
    };
    flash: {
        success?: string;
        error?: string;
    };
    isPaid: boolean;
}

export default function Index({ auth, digests, flash, isPaid }: Props) {
    const [confirmingDigestDeletion, setConfirmingDigestDeletion] = useState(false);
    const [digestToDelete, setDigestToDelete] = useState<number | null>(null);

    const handleDelete = (id: number) => {
        setDigestToDelete(id);
        setConfirmingDigestDeletion(true);
    };

    const confirmDelete = () => {
        if (digestToDelete) {
            router.delete(route('digests.destroy', digestToDelete), {
                onSuccess: () => setConfirmingDigestDeletion(false),
            });
        }
    };

    const toggleActive = (digest: Digest) => {
        router.put(route('digests.update', digest.id), {
            ...digest,
            scheduled_at: digest.scheduled_at.substring(0, 5),
            is_active: !digest.is_active,
        }, {
            preserveScroll: true
        });
    };

    const hasAnyRunToday = digests.data.some(digest => {
        if (!digest.last_run_at) return false;
        const lastRun = new Date(digest.last_run_at);
        const today = new Date();
        return lastRun.toDateString() === today.toDateString();
    });

    return (
        <AuthenticatedLayout>
            <Head title="My Digests" />

            {/* Hero Header */}
            <div className="relative bg-gradient-to-br from-indigo-50 via-purple-50 to-indigo-100 dark:from-indigo-900 dark:via-purple-900 dark:to-indigo-900 overflow-hidden">
                {/* Decorative blobs */}
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-indigo-500/10 dark:bg-white/5 blur-3xl" />
                    <div className="absolute -bottom-32 -left-32 w-80 h-80 rounded-full bg-purple-500/10 dark:bg-purple-400/10 blur-3xl" />
                </div>

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                        <div>
                            <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white tracking-tight">
                                My Digests
                            </h1>
                            <p className="mt-2 text-indigo-900 dark:text-indigo-100 text-lg max-w-xl">
                                Manage your custom AI-powered summaries from specific channels or search terms.
                            </p>
                        </div>
                        <div className="flex items-stretch gap-3">
                            <Link
                                href={route('digest_runs.index')}
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-white/50 dark:bg-white/10 hover:bg-white/80 dark:hover:bg-white/20 backdrop-blur-sm border border-gray-200/50 dark:border-white/10 text-gray-900 dark:text-white px-6 py-3 text-sm font-semibold shadow-sm transition-all hover:shadow-md active:scale-95"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                All History
                            </Link>
                            <Link
                                href={route('digests.create')}
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 dark:bg-indigo-500 hover:bg-indigo-500 dark:hover:bg-indigo-400 text-white px-6 py-3 text-sm font-semibold shadow-sm transition-all hover:shadow-md active:scale-95"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Create New Digest
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                {flash.success && (
                    <div className="mb-8 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 p-4 border border-emerald-200 dark:border-emerald-500/20 shadow-sm flex items-center gap-3 animate-in slide-in-from-top-4">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <p className="text-sm font-medium text-emerald-800 dark:text-emerald-200">{flash.success}</p>
                    </div>
                )}

                {digests.data.length === 0 ? (
                    <div className="rounded-[2rem] border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm shadow-sm">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/20 shadow-inner">
                            <svg className="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                            </svg>
                        </div>
                        <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">No custom digests</h3>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-6">
                            Create your first custom digest to aggregate multiple channels or topics into a single daily or weekly summary.
                        </p>
                        <Link
                            href={route('digests.create')}
                            className="inline-flex items-center px-6 py-3 rounded-xl bg-indigo-600 dark:bg-indigo-500 text-white font-medium hover:bg-indigo-700 dark:hover:bg-indigo-400 transition-colors shadow-md hover:shadow-lg"
                        >
                            Create Digest
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        {digests.data.map((digest) => (
                            <div key={digest.id} className="group relative bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-3xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm hover:shadow-xl dark:shadow-none hover:border-indigo-300/50 dark:hover:border-indigo-500/50 transition-all duration-300 overflow-hidden flex flex-col">
                                <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 opacity-0 group-hover:opacity-100 transition-opacity" />
                                
                                <div className="p-6 flex-1 flex flex-col">
                                    <div className="flex justify-between items-start mb-6">
                                        <div className="flex-1 pr-4">
                                            <h3 className="text-lg font-bold text-gray-900 dark:text-white line-clamp-2 leading-tight" title={digest.name}>
                                                {digest.name}
                                            </h3>
                                            
                                            {!isPaid && hasAnyRunToday && digest.last_time_change_at && (
                                                (() => {
                                                    const changeDate = new Date(digest.last_time_change_at);
                                                    const isToday = changeDate.toDateString() === new Date().toDateString();
                                                    return isToday ? (
                                                        <div className="mt-2 flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                                                            <svg className="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                            </svg>
                                                            <span className="text-[10px] font-medium text-amber-700 dark:text-amber-300 uppercase tracking-wider">
                                                                Changes apply tomorrow
                                                            </span>
                                                        </div>
                                                    ) : null;
                                                })()
                                            )}
                                        </div>
                                        <button
                                            onClick={() => toggleActive(digest)}
                                            className="focus:outline-none flex-shrink-0 mt-0.5 group/toggle"
                                            title={digest.is_active ? 'Pause Digest' : 'Activate Digest'}
                                        >
                                            <div className="relative">
                                                <div className={`block w-11 h-6 rounded-full transition-colors duration-300 ${digest.is_active ? 'bg-indigo-500 shadow-inner' : 'bg-gray-200 dark:bg-gray-700 shadow-inner'}`}></div>
                                                <div className={`absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full shadow-sm transition-transform duration-300 ${digest.is_active ? 'transform translate-x-5' : ''}`}></div>
                                            </div>
                                        </button>
                                    </div>

                                    <div className="space-y-4 flex-1">
                                        <div className="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/60 p-3 rounded-xl border border-gray-100 dark:border-gray-700/50">
                                            <div className="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mr-3 flex-shrink-0">
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div className="capitalize">{digest.frequency}{digest.frequency === 'weekly' && ` on ${digest.day_of_week}`}</div>
                                                <div className="text-xs text-gray-500 dark:text-gray-400">{digest.scheduled_at}</div>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/60 p-3 rounded-xl border border-gray-100 dark:border-gray-700/50">
                                            <div className="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 flex items-center justify-center mr-3 flex-shrink-0">
                                                {digest.mode === 'channels' ? (
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                    </svg>
                                                ) : (
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                    </svg>
                                                )}
                                            </div>
                                            <div className="truncate pr-2">
                                                {digest.mode === 'channels' ? (
                                                    <>{digest.channels_count} Channels</>
                                                ) : digest.mode === 'search_term' ? (
                                                    <>Search: "{digest.search_term || ''}"</>
                                                ) : (
                                                    <>Mixed ({digest.channels_count} + "{digest.search_term || ''}")</>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="border-t border-gray-100 dark:border-gray-700/50 bg-gray-50/80 dark:bg-gray-800/80 px-4 py-4 flex gap-2">
                                    <Link
                                        href={route('digest_runs.index', { digest_id: digest.id })}
                                        className="flex-1 text-center py-2.5 rounded-xl text-sm font-semibold text-indigo-600 dark:text-white bg-indigo-50 dark:bg-indigo-600 hover:bg-indigo-100 dark:hover:bg-indigo-500 transition-colors shadow-sm"
                                    >
                                        History
                                    </Link>
                                    <Link
                                        href={route('digests.edit', digest.id)}
                                        className="flex-1 text-center py-2.5 rounded-xl text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors shadow-sm"
                                    >
                                        Settings
                                    </Link>
                                    <button
                                        onClick={() => handleDelete(digest.id)}
                                        className="py-2.5 px-3.5 rounded-xl text-sm font-semibold text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border-2 border-transparent hover:border-red-200 dark:hover:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all shadow-sm group/del"
                                        title="Delete Digest"
                                    >
                                        <svg className="w-5 h-5 group-hover/del:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <Pagination 
                    links={digests.links} 
                    meta={{
                        current_page: digests.current_page,
                        from: digests.from,
                        to: digests.to,
                        total: digests.total
                    }} 
                />
            </div>

            <ConfirmationModal
                show={confirmingDigestDeletion}
                onClose={() => setConfirmingDigestDeletion(false)}
                onConfirm={confirmDelete}
                title="Delete Digest"
                content="Are you sure you want to delete this digest? This action cannot be undone."
                confirmText="Delete"
            />
        </AuthenticatedLayout>
    );
}
