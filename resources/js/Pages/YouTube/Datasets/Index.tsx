import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import ConfirmationModal from '@/Components/ConfirmationModal';

interface Dataset {
    id: number;
    name: string;
    channel_url: string;
    scheduled_time: string;
    timezone: string;
    last_synced_at: string | null;
    status: 'idle' | 'syncing' | 'error';
    is_paused: boolean;
    videos_count: number;
}

interface Props extends PageProps {
    datasets: Dataset[];
    flash: {
        success?: string;
        error?: string;
    };
}

export default function Index({ auth, datasets, flash }: Props) {
    const [confirmingDeletion, setConfirmingDeletion] = useState(false);
    const [datasetToDelete, setDatasetToDelete] = useState<number | null>(null);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingDataset, setEditingDataset] = useState<Dataset | null>(null);
    const [showSuccess, setShowSuccess] = useState(false);
    const [showError, setShowError] = useState(false);

    useEffect(() => {
        if (flash.success) {
            setShowSuccess(true);
            const timer = setTimeout(() => setShowSuccess(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash.success]);

    useEffect(() => {
        if (flash.error) {
            setShowError(true);
            const timer = setTimeout(() => setShowError(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash.error]);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        channel_url: '',
        scheduled_time: '02:00',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    });

    const createDataset = (e: React.FormEvent) => {
        e.preventDefault();
        
        if (editingDataset) {
            router.put(route('datasets.update', editingDataset.id), {
                name: data.name,
                scheduled_time: data.scheduled_time,
            }, {
                onSuccess: () => {
                    closeModal();
                },
            });
        } else {
            post(route('datasets.store'), {
                onSuccess: () => {
                    closeModal();
                },
            });
        }
    };

    const openEditModal = (dataset: Dataset) => {
        setEditingDataset(dataset);
        setData({
            name: dataset.name,
            channel_url: dataset.channel_url,
            scheduled_time: dataset.scheduled_time,
            timezone: dataset.timezone,
        });
        setIsCreateModalOpen(true);
    };

    const closeModal = () => {
        setIsCreateModalOpen(false);
        setEditingDataset(null);
        reset();
    };

    const handleDelete = (id: number) => {
        setDatasetToDelete(id);
        setConfirmingDeletion(true);
    };

    const confirmDelete = () => {
        if (datasetToDelete) {
            router.delete(route('datasets.destroy', datasetToDelete), {
                onSuccess: () => setConfirmingDeletion(false),
            });
        }
    };

    const togglePause = (dataset: Dataset) => {
        router.patch(route('datasets.toggle', dataset.id), {}, {
            preserveScroll: true
        });
    };

    const triggerSync = (dataset: Dataset, fullSync: boolean = false) => {
        if (dataset.status === 'syncing') return;
        router.post(route('datasets.sync', dataset.id), { 
            full_sync: fullSync 
        }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Knowledge Base" />

            <div className="relative bg-gradient-to-br from-indigo-50 via-purple-50 to-indigo-100 dark:from-indigo-900 dark:via-purple-900 dark:to-indigo-900 overflow-hidden">
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-indigo-500/10 dark:bg-white/5 blur-3xl" />
                    <div className="absolute -bottom-32 -left-32 w-80 h-80 rounded-full bg-purple-500/10 dark:bg-purple-400/10 blur-3xl" />
                </div>

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                        <div>
                            <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white tracking-tight">
                                AI Knowledge Base
                            </h1>
                            <p className="mt-2 text-indigo-900 dark:text-indigo-100 text-lg max-w-xl">
                                Build daily-updating datasets from YouTube channels. Download the aggregated Markdown files to feed into your custom AI agents.
                            </p>
                        </div>
                        <div className="flex items-stretch gap-3">
                            <button
                                onClick={() => setIsCreateModalOpen(true)}
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 dark:bg-indigo-500 hover:bg-indigo-500 dark:hover:bg-indigo-400 text-white px-6 py-3 text-sm font-semibold shadow-sm transition-all hover:shadow-md active:scale-95"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Create Dataset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                {showSuccess && flash.success && (
                    <div className="mb-8 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 p-4 border border-emerald-200 dark:border-emerald-500/20 shadow-sm flex items-center justify-between gap-3 animate-in fade-in slide-in-from-top-4 duration-300">
                        <div className="flex items-center gap-3">
                            <svg className="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                            <p className="text-sm font-medium text-emerald-800 dark:text-emerald-200">{flash.success}</p>
                        </div>
                        <button onClick={() => setShowSuccess(false)} className="text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-200 transition-colors">
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                )}
                {showError && flash.error && (
                    <div className="mb-8 rounded-2xl bg-red-50 dark:bg-red-500/10 p-4 border border-red-200 dark:border-red-500/20 shadow-sm flex items-center justify-between gap-3 animate-in fade-in slide-in-from-top-4 duration-300">
                        <div className="flex items-center gap-3">
                            <svg className="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <p className="text-sm font-medium text-red-800 dark:text-red-200">{flash.error}</p>
                        </div>
                        <button onClick={() => setShowError(false)} className="text-red-400 hover:text-red-600 dark:hover:text-red-200 transition-colors">
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                )}

                {datasets.length === 0 ? (
                    <div className="rounded-[2rem] border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm shadow-sm">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/20 shadow-inner">
                            <svg className="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                            </svg>
                        </div>
                        <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">No datasets yet</h3>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-6">
                            Create your first dataset to start aggregating transcripts into a single knowledge file perfectly formatted for AI.
                        </p>
                        <button
                            onClick={() => setIsCreateModalOpen(true)}
                            className="inline-flex items-center px-6 py-3 rounded-xl bg-indigo-600 dark:bg-indigo-500 text-white font-medium hover:bg-indigo-700 dark:hover:bg-indigo-400 transition-colors shadow-md hover:shadow-lg"
                        >
                            Create Dataset
                        </button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {datasets.map((dataset) => (
                            <div key={dataset.id} className="group relative bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-3xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm hover:shadow-xl dark:shadow-none hover:border-indigo-300/50 dark:hover:border-indigo-500/50 transition-all duration-300 overflow-hidden flex flex-col">
                                <div className="p-6 flex-1 flex flex-col">
                                    <div className="flex justify-between items-start mb-4">
                                        <div className="flex-1 pr-4">
                                            <h3 className="text-lg font-bold text-gray-900 dark:text-white line-clamp-2 leading-tight">
                                                {dataset.name}
                                            </h3>
                                            <a href={dataset.channel_url} target="_blank" rel="noreferrer" className="text-xs text-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400 truncate block mt-1">
                                                {dataset.channel_url}
                                            </a>
                                        </div>
                                            <div className="flex items-center gap-2">
                                                <button
                                                    onClick={() => openEditModal(dataset)}
                                                    className="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all"
                                                    title="Edit Dataset"
                                                >
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    onClick={() => togglePause(dataset)}
                                                    className="focus:outline-none flex-shrink-0 group/toggle flex flex-col items-center"
                                                    title={dataset.is_paused ? 'Resume Syncing' : 'Pause Syncing'}
                                                >
                                                    <div className="relative">
                                                        <div className={`block w-9 h-5 rounded-full transition-colors duration-300 ${!dataset.is_paused ? 'bg-emerald-500 shadow-inner' : 'bg-gray-300 dark:bg-gray-700 shadow-inner'}`}></div>
                                                        <div className={`absolute left-0.5 top-0.5 bg-white w-4 h-4 rounded-full shadow-sm transition-transform duration-300 ${!dataset.is_paused ? 'transform translate-x-4' : ''}`}></div>
                                                    </div>
                                                    <span className="text-[9px] uppercase font-bold text-gray-400 mt-0.5">{dataset.is_paused ? 'Paused' : 'Active'}</span>
                                                </button>
                                            </div>
                                    </div>

                                    <div className="space-y-3 flex-1">
                                        <div className="flex items-center text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/60 p-3 rounded-xl border border-gray-100 dark:border-gray-700/50">
                                            <div className="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center mr-3">
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </div>
                                            <div>
                                                <div className="font-semibold text-gray-900 dark:text-white">Daily at {dataset.scheduled_time}</div>
                                                <div className="text-[11px] text-gray-500">{dataset.timezone}</div>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/60 p-3 rounded-xl border border-gray-100 dark:border-gray-700/50">
                                            <div className="flex items-center">
                                                <div className="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 flex items-center justify-center mr-3">
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                                </div>
                                                <div>
                                                    <span className="font-bold text-gray-900 dark:text-white">{dataset.videos_count}</span> Videos
                                                </div>
                                            </div>

                                            {dataset.status === 'syncing' ? (
                                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-xs font-semibold animate-pulse border border-indigo-200 dark:border-indigo-500/20">
                                                    Syncing...
                                                </span>
                                            ) : dataset.status === 'error' ? (
                                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 text-xs font-semibold">
                                                    Error
                                                </span>
                                            ) : (
                                                <span className="text-xs text-gray-500">
                                                    {dataset.last_synced_at ? 'Synced' : 'Ready'}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 dark:border-gray-700/50 bg-gray-50/80 dark:bg-gray-800/80 px-4 py-4 flex flex-wrap gap-2">
                                    <button
                                        onClick={() => triggerSync(dataset)}
                                        disabled={dataset.status === 'syncing'}
                                        className="flex-1 text-center py-2 rounded-xl text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
                                        title="Catch up videos since last run"
                                    >
                                        Sync Now
                                    </button>
                                    <button
                                        onClick={() => triggerSync(dataset, true)}
                                        disabled={dataset.status === 'syncing'}
                                        className="flex-1 text-center py-2 rounded-xl text-sm font-semibold text-purple-700 dark:text-purple-300 bg-white dark:bg-gray-700 border border-purple-200 dark:border-purple-900/50 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
                                        title="Sync entire channel history"
                                    >
                                        Full Sync
                                    </button>
                                    <a
                                        href={route('datasets.download', dataset.id)}
                                        className="flex-1 text-center py-2 rounded-xl text-sm font-semibold text-indigo-600 dark:text-white bg-indigo-50 dark:bg-indigo-600 hover:bg-indigo-100 dark:hover:bg-indigo-500 transition-colors shadow-sm whitespace-nowrap"
                                        target="_blank" rel="noreferrer"
                                    >
                                        Download
                                    </a>
                                    <button
                                        onClick={() => handleDelete(dataset.id)}
                                        className="py-2.5 px-3 rounded-xl text-sm font-semibold text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border focus:outline-none border-gray-200 dark:border-gray-600 hover:border-red-200 dark:hover:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all shadow-sm group/del"
                                    >
                                        <svg className="w-5 h-5 group-hover/del:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <ConfirmationModal show={confirmingDeletion} onClose={() => setConfirmingDeletion(false)} onConfirm={confirmDelete} title="Delete Dataset" content="Delete this dataset and its knowledge Markdown file? This action is permanent." confirmText="Delete" />

            {/* Create Modal */}
            {isCreateModalOpen && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center px-4 overflow-y-auto w-full">
                    <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" onClick={closeModal}></div>
                    <div className="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl p-6 sm:p-10 w-full max-w-lg transform transition-all my-8 overflow-y-auto max-h-[90vh]">
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                            {editingDataset ? 'Edit Knowledge Base' : 'Create New Knowledge Base'}
                        </h2>
                        <form onSubmit={createDataset} className="space-y-5">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Dataset Name</label>
                                <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} className="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-900 dark:border-gray-600 dark:text-white" required placeholder="e.g. Sales Coaching Persona" />
                                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Channel URL</label>
                                <input 
                                    type="url" 
                                    value={data.channel_url} 
                                    onChange={e => setData('channel_url', e.target.value)} 
                                    className={`mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-900 dark:border-gray-600 dark:text-white ${editingDataset ? 'opacity-50 cursor-not-allowed bg-gray-50' : ''}`} 
                                    required 
                                    placeholder="https://www.youtube.com/@mreflow"
                                    disabled={!!editingDataset}
                                />
                                {editingDataset && <p className="mt-1 text-[10px] text-gray-500 italic">Channel URL cannot be changed after creation.</p>}
                                {errors.channel_url && <p className="mt-1 text-xs text-red-500">{errors.channel_url}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Daily Sync Time</label>
                                    <input type="time" value={data.scheduled_time} onChange={e => setData('scheduled_time', e.target.value)} className="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-900 dark:border-gray-600 dark:text-white" required />
                                    {errors.scheduled_time && <p className="mt-1 text-xs text-red-500">{errors.scheduled_time}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Timezone</label>
                                    <input type="text" disabled value={data.timezone} className="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 text-gray-500 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700" />
                                </div>
                            </div>
                            <div className="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <button type="button" onClick={closeModal} className="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">Cancel</button>
                                <button type="submit" disabled={processing} className="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">
                                    {editingDataset ? 'Update Settings' : 'Create & Start Sync'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
