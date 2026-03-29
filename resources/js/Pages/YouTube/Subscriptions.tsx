import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link, router, usePage } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import { FormEventHandler, useState } from 'react';
import ConfirmationModal from '@/Components/ConfirmationModal';
import Pagination from '@/Components/Pagination';

interface Channel {
    id: number;
    url: string;
    name: string;
    thumbnail_url?: string;
    subscriber_count?: string;
    is_paused: boolean;
}

interface Props {
    auth: any;
    channels: {
        data: Channel[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
        current_page: number;
        from: number;
        to: number;
    };
    flash?: { success?: string };
}

export default function Subscriptions({ auth, channels, flash }: Props) {
    const [channelToUnsubscribe, setChannelToUnsubscribe] = useState<number | null>(null);

    const confirmUnsubscribe = (id: number) => {
        setChannelToUnsubscribe(id);
    };

    const unsubscribe = () => {
        if (!channelToUnsubscribe) return;
        router.delete(route('youtube.subscriptions.destroy', channelToUnsubscribe), {
            preserveScroll: true,
            onFinish: () => setChannelToUnsubscribe(null),
        });
    };

    const handleTogglePause = (channel: Channel) => {
        router.post(route('youtube.subscriptions.toggle', channel.id), {}, {
            preserveScroll: true,
        });
    };

    const { data, setData, post, processing, errors, reset } = useForm({
        url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('youtube.subscriptions.store'), {
            onSuccess: () => reset('url'),
        });
    };

    const handleExtract = (url: string) => {
        if (url.includes('@')) return url.split('/').pop();
        if (url.includes('/channel/')) return 'ID: ' + url.split('/channel/')[1]?.slice(0, 12) + '…';
        return url;
    };

    return (
        <AuthenticatedLayout>
            <Head title="Subscriptions" />

            {/* Flash Message */}
            {flash?.success && (
                <div className="bg-emerald-50 dark:bg-emerald-900/20 border-b border-emerald-200 dark:border-emerald-800">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center gap-3">
                        <svg className="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">{flash.success}</p>
                    </div>
                </div>
            )}

            {/* Hero Header */}
            <div className="relative bg-gradient-to-br from-indigo-50 via-purple-50 to-indigo-100 dark:from-indigo-600 dark:via-purple-600 dark:to-indigo-800 overflow-hidden">
                {/* Decorative blobs */}
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-indigo-500/5 dark:bg-white/5 blur-3xl" />
                    <div className="absolute -bottom-32 -left-32 w-80 h-80 rounded-full bg-purple-500/5 dark:bg-purple-400/10 blur-3xl" />
                </div>

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                        <div>
                            <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white tracking-tight">
                                Subscriptions
                            </h1>
                            <p className="mt-2 text-indigo-900 dark:text-indigo-100 text-lg max-w-xl">
                                Get daily AI-powered summaries of your favorite YouTube channels delivered to your inbox.
                            </p>
                        </div>

                        {/* Controls */}
                        <div className="flex items-stretch gap-3 mt-4 sm:mt-0">
                            {/* Import from YouTube */}
                            <a
                                href={route('youtube.auth.redirect')}
                                className="group flex items-center gap-2 rounded-2xl px-6 py-3 transition-all duration-300 shadow-sm hover:scale-[1.02] active:scale-95 bg-red-500 hover:bg-red-600 text-white hover:shadow-md border border-red-600"
                            >
                                <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                                <span className="text-sm font-semibold">Import from YouTube</span>
                            </a>
                            <Link
                                href={route('digests.index')}
                                className="group flex items-center gap-2 rounded-2xl px-6 py-3 transition-all duration-300 shadow-sm hover:scale-[1.02] active:scale-95 bg-white/50 dark:bg-white/10 backdrop-blur-sm hover:bg-white/80 dark:hover:bg-white/20 border border-indigo-200/50 dark:border-white/20 text-gray-900 dark:text-white hover:shadow-md hover:border-indigo-300/50 dark:hover:border-white/30"
                            >
                                <svg className="w-5 h-5 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span className="text-sm font-semibold">Automate Channels</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                {/* Add Channel — Inline Form */}
                <div className="mb-10">
                    <form onSubmit={submit} className="flex flex-col sm:flex-row gap-3">
                        <div className="relative flex-1">
                            <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.193-9.193a4.5 4.5 0 016.364 6.364l-4.5 4.5a4.5 4.5 0 01-7.244-1.242" />
                                </svg>
                            </div>
                            <input
                                type="url"
                                className="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 pl-12 pr-4 py-4 text-base text-gray-900 dark:text-white placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 shadow-sm transition-shadow hover:shadow-md"
                                value={data.url}
                                onChange={(e) => setData('url', e.target.value)}
                                placeholder="Paste a YouTube channel URL to subscribe…"
                                required
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-8 py-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all disabled:opacity-50 hover:shadow-md"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {processing ? 'Adding…' : 'Subscribe'}
                        </button>
                    </form>
                    <InputError className="mt-2 pl-4" message={errors.url} />
                </div>

                {/* Channel Grid */}
                <div>
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Your Channels
                            {channels.data.length > 0 && (
                                <span className="ml-2 inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                                    {channels.total}
                                </span>
                            )}
                        </h2>
                    </div>

                    {channels.data.length === 0 ? (
                        <div className="rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/20">
                                <svg className="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </div>
                            <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">No subscriptions yet</h3>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                                Paste a YouTube channel URL above to start receiving daily AI summaries of their latest videos.
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                            {channels.data.map((channel) => (
                                <div
                                    key={channel.id}
                                    className="group relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-lg hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden"
                                >
                                    {/* Hover gradient accent */}
                                    <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 opacity-0 group-hover:opacity-100 transition-opacity" />

                                    <div className="p-6 flex flex-col items-center text-center">
                                        {/* Channel Avatar */}
                                        <div className="relative mb-4">
                                            <div className={`w-20 h-20 rounded-full overflow-hidden ring-4 shadow-md transition-all ${channel.is_paused ? 'ring-gray-200 dark:ring-gray-700 grayscale opacity-70' : 'ring-gray-50 dark:ring-gray-700 group-hover:ring-indigo-100 dark:group-hover:ring-indigo-900/50'}`}>
                                                {channel.thumbnail_url ? (
                                                    <img
                                                        src={channel.thumbnail_url}
                                                        alt={channel.name}
                                                        className="w-full h-full object-cover"
                                                    />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600 text-white text-2xl font-bold">
                                                        {channel.name ? channel.name.charAt(0).toUpperCase() : '?'}
                                                    </div>
                                                )}
                                            </div>
                                            {/* Status indicator */}
                                            <div className="absolute -bottom-0.5 -right-0.5 h-5 w-5 rounded-full bg-white dark:bg-gray-800 flex items-center justify-center">
                                                <div className={`h-3 w-3 rounded-full ${channel.is_paused ? 'bg-gray-400' : 'bg-emerald-500'}`} />
                                            </div>
                                        </div>

                                        {/* Name */}
                                        <h3
                                            className="font-bold text-gray-900 dark:text-gray-100 text-base mb-1 truncate w-full"
                                            title={channel.name}
                                        >
                                            {channel.name || 'Unknown Channel'}
                                        </h3>

                                        {/* Subscribers */}
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                            {channel.subscriber_count
                                                ? `${channel.subscriber_count} subscribers`
                                                : 'Subscribers hidden'}
                                        </p>

                                        {/* Channel handle */}
                                        <a
                                            href={channel.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1 text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium mb-4 transition-colors"
                                        >
                                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                            </svg>
                                            {handleExtract(channel.url)}
                                        </a>

                                        {/* Unsubscribe */}
                                        {/* Actions */}
                                        <div className="w-full grid grid-cols-2 gap-2">
                                            <button
                                                onClick={() => handleTogglePause(channel)}
                                                className={`w-full rounded-xl border px-2 py-2 text-xs font-medium transition-all ${channel.is_paused
                                                    ? 'border-emerald-200 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20'
                                                    : 'border-amber-200 dark:border-amber-800 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20'
                                                    }`}
                                            >
                                                {channel.is_paused ? 'Resume' : 'Pause'}
                                            </button>
                                            <button
                                                onClick={() => confirmUnsubscribe(channel.id)}
                                                className="w-full rounded-xl border border-gray-200 dark:border-gray-600 px-2 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-red-600 hover:border-red-300 hover:bg-red-50 dark:hover:text-red-400 dark:hover:border-red-800 dark:hover:bg-red-900/10 transition-all"
                                            >
                                                Unsubscribe
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <Pagination 
                    links={channels.links} 
                    meta={{
                        current_page: channels.current_page,
                        from: channels.from,
                        to: channels.to,
                        total: channels.total
                    }} 
                />
            </div>

            <ConfirmationModal
                show={channelToUnsubscribe !== null}
                title="Unsubscribe Channel"
                content="Are you sure you want to unsubscribe from this channel? You will no longer receive summaries for new videos."
                confirmText="Unsubscribe"
                onClose={() => setChannelToUnsubscribe(null)}
                onConfirm={unsubscribe}
                processing={processing}
            />
        </AuthenticatedLayout>
    );
}
