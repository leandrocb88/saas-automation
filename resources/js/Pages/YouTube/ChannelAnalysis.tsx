import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FreqAskedQuestions from '@/Components/FreqAskedQuestions';
import { Head, useForm, Link, usePage } from '@inertiajs/react';
import { FormEventHandler, useState, useEffect } from 'react';

export default function ChannelAnalysis({ auth, canSubmit }: { auth: any, canSubmit: boolean }) {
    const { flash } = usePage().props as any;
    const isPaid = auth.user && auth.user.stripe_id;

    const [batchMode, setBatchMode] = useState(false);
    const [showLongRunningMessage, setShowLongRunningMessage] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        urls: '',
        max_videos: 10,
        sort_order: 'date',
        date_range: 'any',
    });

    useEffect(() => {
        let timer: NodeJS.Timeout;
        if (processing) {
            setShowLongRunningMessage(false);
            timer = setTimeout(() => {
                setShowLongRunningMessage(true);
            }, 60000);
        } else {
            setShowLongRunningMessage(false);
        }
        return () => clearTimeout(timer);
    }, [processing]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('youtube.channel.process'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Channel Analyzer" />

            {/* Hero Section */}
            <div className="relative overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-br from-indigo-950 via-purple-950 to-slate-900" />
                <div className="absolute top-20 right-20 w-72 h-72 bg-indigo-500/20 rounded-full blur-3xl animate-pulse" />
                <div className="absolute bottom-10 left-10 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl" />

                <div className="relative py-16 lg:py-24">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center max-w-3xl mx-auto">
                            <div className="inline-flex items-center rounded-full border border-indigo-500/30 bg-indigo-500/10 backdrop-blur-sm px-4 py-1.5 text-sm font-medium text-indigo-300 mb-8">
                                <span className="flex h-2 w-2 rounded-full bg-indigo-400 mr-2 animate-pulse" />
                                Deep Channel Insights
                            </div>
                            <h1 className="text-4xl font-bold tracking-tight text-white sm:text-6xl mb-6 leading-[1.1]">
                                Analyze Entire{' '}
                                <span className="bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">
                                    Channels
                                </span>{' '}
                                in One Go.
                            </h1>
                            <p className="mt-4 text-xl text-gray-300/80 max-w-2xl mx-auto">
                                Extract transcripts, summaries, and insights from multiple videos at once.
                            </p>

                            {!canSubmit && (
                                <div className="mt-8 bg-amber-500/10 backdrop-blur-sm border border-amber-500/20 rounded-xl p-4 text-left max-w-2xl mx-auto">
                                    <div className="flex items-start gap-3">
                                        <svg className="h-5 w-5 text-amber-400 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                                        </svg>
                                        <p className="text-sm text-amber-200">
                                            <span className="font-bold">Members Only Feature.</span> Using the Channel Analyzer requires an active subscription.{' '}
                                            <Link href={route('plans')} className="font-medium text-amber-300 underline hover:text-amber-200">
                                                Upgrade your plan
                                            </Link>
                                            {' '}to unlock this powerful tool.
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <div className="py-12 bg-gray-50 dark:bg-gray-900 min-h-[50vh]">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                    {/* Alerts */}
                    {showLongRunningMessage && (
                        <div className="mx-auto max-w-2xl mb-6">
                            <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4 rounded-xl shadow-sm">
                                <div className="flex items-start gap-3">
                                    <svg className="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                    </svg>
                                    <div>
                                        <h3 className="text-sm font-semibold text-blue-800 dark:text-blue-300">Analysis taking longer than expected</h3>
                                        <p className="mt-1 text-sm text-blue-700 dark:text-blue-400">
                                            Don't worry! Deep analysis of many videos can take up to 10 minutes.
                                            You can safely leave this page and check your <Link href={route('youtube.history')} className="font-bold underline">History</Link> later.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {flash?.success && (
                        <div className="mx-auto max-w-2xl mb-6">
                            <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 rounded-xl shadow-sm">
                                <div className="flex items-center gap-3">
                                    <svg className="h-5 w-5 text-green-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clipRule="evenodd" />
                                    </svg>
                                    <p className="text-sm text-green-700 dark:text-green-300 font-medium">{flash.success}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {Object.keys(errors).filter(k => k !== 'urls').length > 0 && (
                        <div className="mx-auto max-w-2xl mb-6">
                            <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 rounded-xl shadow-sm">
                                <div className="flex items-center gap-3">
                                    <svg className="h-5 w-5 text-red-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" />
                                    </svg>
                                    <p className="text-sm text-red-700 dark:text-red-300">
                                        {errors[Object.keys(errors).filter(k => k !== 'urls')[0] as keyof typeof errors]}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Form Card */}
                    <div className="mx-auto max-w-2xl bg-white dark:bg-gray-800 rounded-2xl shadow-xl ring-1 ring-gray-200/50 dark:ring-gray-700/50 overflow-hidden">
                        <div className="p-8">
                            <form onSubmit={submit} className="space-y-6">
                                {/* Batch Toggle */}
                                <div className="flex justify-end">
                                    <label className="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="sr-only peer"
                                            checked={batchMode}
                                            onChange={(e) => setBatchMode(e.target.checked)}
                                        />
                                        <div className="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600" />
                                        <span className="ml-3 text-sm font-medium text-gray-900 dark:text-gray-300">Bulk Channels</span>
                                    </label>
                                </div>

                                {/* Channel Input */}
                                <div>
                                    <label htmlFor="urls" className="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                        {batchMode ? 'Channel URLs (one per line, max 10)' : 'Channel URL'}
                                    </label>
                                    {!batchMode ? (
                                        <input
                                            type="url"
                                            name="urls"
                                            id="urls"
                                            className="block w-full rounded-xl border-0 py-3.5 pl-4 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm dark:bg-gray-700 dark:ring-gray-600 dark:text-white dark:placeholder-gray-400"
                                            placeholder="https://www.youtube.com/@ChannelName"
                                            value={data.urls}
                                            onChange={(e) => setData('urls', e.target.value)}
                                            required
                                        />
                                    ) : (
                                        <textarea
                                            name="urls"
                                            id="urls"
                                            rows={4}
                                            className="block w-full rounded-xl border-0 py-3.5 pl-4 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm dark:bg-gray-700 dark:ring-gray-600 dark:text-white dark:placeholder-gray-400"
                                            placeholder={'https://www.youtube.com/@Channel1\nhttps://www.youtube.com/@Channel2'}
                                            value={data.urls}
                                            onChange={(e) => setData('urls', e.target.value)}
                                            required
                                        />
                                    )}
                                    {/* @ts-ignore */}
                                    {errors.urls && <p className="mt-2 text-sm text-red-600">{errors.urls}</p>}
                                </div>

                                {/* Options grid */}
                                <div className="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-3">
                                    <div>
                                        <label htmlFor="max_videos" className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
                                            Max Videos
                                        </label>
                                        <input
                                            type="number"
                                            name="max_videos"
                                            id="max_videos"
                                            min={1}
                                            max={100}
                                            className="block w-full rounded-xl border-0 py-2.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm dark:bg-gray-700 dark:ring-gray-600 dark:text-white"
                                            value={data.max_videos}
                                            onChange={(e) => setData('max_videos', parseInt(e.target.value))}
                                        />
                                    </div>
                                    <div>
                                        <label htmlFor="sort_order" className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
                                            Sort By
                                        </label>
                                        <select
                                            id="sort_order"
                                            name="sort_order"
                                            className="block w-full rounded-xl border-0 py-2.5 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm dark:bg-gray-700 dark:ring-gray-600 dark:text-white"
                                            value={data.sort_order}
                                            onChange={(e) => setData('sort_order', e.target.value)}
                                        >
                                            <option value="date">Newest</option>
                                            <option value="viewCount">Most Popular</option>
                                            <option value="relevance">Relevance</option>
                                            <option value="rating">Rating</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label htmlFor="date_range" className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
                                            Date Range
                                        </label>
                                        <select
                                            id="date_range"
                                            name="date_range"
                                            className="block w-full rounded-xl border-0 py-2.5 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm dark:bg-gray-700 dark:ring-gray-600 dark:text-white"
                                            value={data.date_range}
                                            onChange={(e) => setData('date_range', e.target.value)}
                                        >
                                            <option value="any">Any Time</option>
                                            <option value="today">Last 24 Hours</option>
                                            <option value="week">Last 7 Days</option>
                                            <option value="month">Last 30 Days</option>
                                            <option value="year">Last Year</option>
                                        </select>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing || !canSubmit}
                                    className="w-full rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-3.5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:from-indigo-700 hover:to-purple-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 transition-all duration-200"
                                >
                                    {processing ? 'Processing Channel...' : (canSubmit ? 'Analyze Channel' : 'Upgrade to Analyze')}
                                </button>
                            </form>
                        </div>
                    </div>

                    {/* FAQ */}
                    <div className="mx-auto max-w-3xl mt-16 mb-16">
                        <FreqAskedQuestions />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
