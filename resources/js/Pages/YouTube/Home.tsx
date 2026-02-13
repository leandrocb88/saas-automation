import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FreqAskedQuestions from '@/Components/FreqAskedQuestions';
import { Head, useForm, Link } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function YouTubeHome({ auth, errors: propErrors, plan, user }: { auth: any, errors: any, plan: string, user: any }) {
    const isPaid = auth.user && auth.user.stripe_id;
    const [batchMode, setBatchMode] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        urls: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('youtube.process'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="YouTube Analyzer" />

            {/* Hero Section */}
            <div className="relative overflow-hidden">
                {/* Gradient background */}
                {/* Gradient background */}
                <div className="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-purple-50 dark:from-slate-950 dark:via-slate-900 dark:to-black" />
                {/* Decorative orbs */}
                <div className="absolute top-20 left-10 w-72 h-72 bg-red-500/10 rounded-full blur-3xl animate-pulse" />
                <div className="absolute bottom-10 right-10 w-96 h-96 bg-orange-500/5 rounded-full blur-3xl" />
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-red-600/5 rounded-full blur-3xl opacity-50" />

                <div className="relative py-24 lg:py-36">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center max-w-3xl mx-auto">
                            {/* Badge */}
                            <div className="inline-flex items-center rounded-full border border-red-500/30 bg-red-500/10 backdrop-blur-sm px-4 py-1.5 text-sm font-medium text-red-700 dark:text-red-300 mb-8">
                                <span className="flex h-2 w-2 rounded-full bg-red-400 mr-2 animate-pulse" />
                                AI-Powered Transcripts
                            </div>

                            <h1 className="text-5xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-7xl mb-6 leading-[1.1]">
                                Turn YouTube Videos into{' '}
                                <span className="bg-gradient-to-r from-red-400 to-orange-400 bg-clip-text text-transparent">
                                    Insights
                                </span>{' '}
                                Instantly.
                            </h1>
                            <p className="mt-4 text-xl text-gray-600 dark:text-gray-300/80 mb-12 max-w-2xl mx-auto">
                                Paste a URL, get a full transcript and AI summary in seconds. Stop watching, start reading, save time.
                            </p>

                            {/* Input Card */}
                            <div className="max-w-xl mx-auto bg-white/50 dark:bg-white/5 backdrop-blur-xl rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-2xl dark:shadow-none">
                                <form onSubmit={submit}>
                                    {/* Batch Mode Toggle */}
                                    <div className="flex justify-end mb-3">
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                className="sr-only peer"
                                                checked={batchMode}
                                                disabled={!isPaid}
                                                onChange={(e) => setBatchMode(e.target.checked)}
                                            />
                                            <div className={`
                                                w-11 h-6 bg-gray-200 dark:bg-white/10 rounded-full peer
                                                peer-focus:ring-4 peer-focus:ring-red-500/20
                                                peer-checked:after:translate-x-full
                                                peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px]
                                                after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full
                                                after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500
                                                ${!isPaid ? 'opacity-50 cursor-not-allowed' : ''}
                                            `} />
                                            <span className="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                                Batch Mode
                                                {!isPaid && <span className="text-xs bg-amber-500/20 text-amber-600 dark:text-amber-300 px-2 py-0.5 rounded-full border border-amber-500/20">Pro</span>}
                                            </span>
                                        </label>
                                    </div>

                                    <div className="relative mb-3">
                                        {!batchMode ? (
                                            <div className="relative flex items-center">
                                                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <input
                                                    type="url"
                                                    name="urls"
                                                    className="block w-full rounded-xl border-0 bg-white dark:bg-white/10 pl-12 pr-32 py-4 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-white/10 focus:ring-2 focus:ring-red-500 sm:text-lg backdrop-blur-sm"
                                                    placeholder="https://www.youtube.com/watch?v=..."
                                                    value={data.urls}
                                                    onChange={(e) => setData('urls', e.target.value)}
                                                    required={!batchMode}
                                                />
                                                <button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="absolute right-2 top-2 bottom-2 rounded-lg bg-gradient-to-r from-red-500 to-orange-500 px-6 py-2 text-sm font-semibold text-white shadow-lg shadow-red-500/25 hover:shadow-red-500/40 hover:from-red-600 hover:to-orange-600 transition-all duration-200 disabled:opacity-50"
                                                >
                                                    {processing ? 'Analyzing...' : 'Analyze'}
                                                </button>
                                            </div>
                                        ) : (
                                            <div className="relative">
                                                <textarea
                                                    name="urls"
                                                    rows={5}
                                                    className="block w-full rounded-xl border-0 bg-white dark:bg-white/10 p-4 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-white/10 focus:ring-2 focus:ring-red-500 sm:text-lg backdrop-blur-sm"
                                                    placeholder={'https://www.youtube.com/watch?v=...\nhttps://www.youtube.com/watch?v=...\n(Max 100 videos)'}
                                                    value={data.urls}
                                                    onChange={(e) => setData('urls', e.target.value)}
                                                    required={batchMode}
                                                />
                                                <button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="mt-3 w-full rounded-xl bg-gradient-to-r from-red-500 to-orange-500 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-red-500/25 hover:shadow-red-500/40 hover:from-red-600 hover:to-orange-600 transition-all duration-200 disabled:opacity-50"
                                                >
                                                    {processing ? 'Processing Batch...' : 'Analyze All'}
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    {/* @ts-ignore */}
                                    {errors.urls && <p className="mt-2 text-sm text-red-400 text-left pl-4">{errors.urls}</p>}
                                    {/* @ts-ignore */}
                                    {errors.limit && <p className="mt-2 text-sm text-red-400 text-left pl-4">{errors.limit}</p>}
                                </form>
                            </div>

                            {/* Trust badges */}
                            <div className="mt-10 flex justify-center gap-8 text-gray-400">
                                <div className="flex items-center gap-2">
                                    <svg className="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span className="text-sm">99% Accuracy</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <svg className="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span className="text-sm">Instant Results</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Features Section */}
            <div className="py-24 bg-white dark:bg-gray-900">
                <div className="mx-auto max-w-7xl px-6 lg:px-8">
                    <div className="mx-auto max-w-2xl lg:text-center">
                        <div className="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-sm font-semibold text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 mb-4">
                            Everything you need
                        </div>
                        <p className="mt-2 text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                            Unlock the power of video content
                        </p>
                        <p className="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-400">
                            Whether you're a casual viewer, a student, or a power user, we have the tools to help you consume content faster.
                        </p>
                    </div>
                    <div className="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-none">
                        <dl className="grid max-w-xl grid-cols-1 gap-8 lg:max-w-none lg:grid-cols-3">
                            {/* Feature 1 */}
                            <div className="group relative flex flex-col rounded-2xl bg-gray-50 dark:bg-gray-800/50 p-8 ring-1 ring-gray-200/50 dark:ring-gray-700/50 hover:ring-indigo-500/30 hover:shadow-lg hover:shadow-indigo-500/5 transition-all duration-300">
                                <dt className="flex items-center gap-x-3 text-base font-semibold leading-7 text-gray-900 dark:text-white">
                                    <div className="h-12 w-12 flex items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-orange-500 shadow-lg shadow-red-500/20 group-hover:shadow-red-500/30 transition-shadow">
                                        <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    Video Analysis
                                </dt>
                                <dd className="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                    <p className="flex-auto">
                                        Paste a single URL or a batch of URLs. Get instant transcripts and AI-generated summaries with key takeaways.
                                    </p>
                                    <p className="mt-6">
                                        <span className="text-sm font-semibold leading-6 text-indigo-600 dark:text-indigo-400 group-hover:text-indigo-500 transition-colors">Single & Batch Mode &rarr;</span>
                                    </p>
                                </dd>
                            </div>
                            {/* Feature 2 */}
                            <div className="group relative flex flex-col rounded-2xl bg-gray-50 dark:bg-gray-800/50 p-8 ring-1 ring-gray-200/50 dark:ring-gray-700/50 hover:ring-indigo-500/30 hover:shadow-lg hover:shadow-indigo-500/5 transition-all duration-300">
                                <dt className="flex items-center gap-x-3 text-base font-semibold leading-7 text-gray-900 dark:text-white">
                                    <div className="h-12 w-12 flex items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 shadow-lg shadow-indigo-500/20 group-hover:shadow-indigo-500/30 transition-shadow">
                                        <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                    Channel Insights
                                </dt>
                                <dd className="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                    <p className="flex-auto">
                                        Analyze entire channels. Sort by views, date, or relevance to find the hidden gems without watching hours of footage.
                                    </p>
                                    <p className="mt-6">
                                        <Link href={route('youtube.channel')} className="text-sm font-semibold leading-6 text-indigo-600 dark:text-indigo-400 group-hover:text-indigo-500 transition-colors">Explore Channels &rarr;</Link>
                                    </p>
                                </dd>
                            </div>
                            {/* Feature 3 */}
                            <div className="group relative flex flex-col rounded-2xl bg-gray-50 dark:bg-gray-800/50 p-8 ring-1 ring-gray-200/50 dark:ring-gray-700/50 hover:ring-indigo-500/30 hover:shadow-lg hover:shadow-indigo-500/5 transition-all duration-300">
                                <dt className="flex items-center gap-x-3 text-base font-semibold leading-7 text-gray-900 dark:text-white">
                                    <div className="h-12 w-12 flex items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-pink-500 shadow-lg shadow-amber-500/20 group-hover:shadow-amber-500/30 transition-shadow">
                                        <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    Daily Digest
                                </dt>
                                <dd className="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                    <p className="flex-auto">
                                        Subscribe to your favorite channels directly on our platform. We'll send you a daily email summary of their latest uploads.
                                    </p>
                                    <p className="mt-6">
                                        <Link href={route('youtube.subscriptions')} className="text-sm font-semibold leading-6 text-indigo-600 dark:text-indigo-400 group-hover:text-indigo-500 transition-colors">Manage Subscriptions &rarr;</Link>
                                    </p>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {/* Mission Section */}
            <div className="py-20 bg-gradient-to-b from-gray-50 to-white dark:from-gray-800 dark:to-gray-900">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg shadow-indigo-500/20 mb-8">
                        <svg className="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </div>
                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl mb-4">
                        Keep it simple,
                        <br />
                        <span className="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                            make it powerful.
                        </span>
                    </h2>
                    <p className="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                        We believe powerful tools don't have to be complicated. Our AI transforms hours of video content
                        into actionable insights with just one click.
                    </p>
                    <div className="mt-12 grid grid-cols-3 gap-8 text-center">
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 ring-1 ring-gray-200/50 dark:ring-gray-700/50 shadow-sm">
                            <div className="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">99%</div>
                            <div className="text-sm text-gray-500 dark:text-gray-400 mt-1 font-medium">Accuracy Rate</div>
                        </div>
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 ring-1 ring-gray-200/50 dark:ring-gray-700/50 shadow-sm">
                            <div className="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">&lt;5s</div>
                            <div className="text-sm text-gray-500 dark:text-gray-400 mt-1 font-medium">Processing Time</div>
                        </div>
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 ring-1 ring-gray-200/50 dark:ring-gray-700/50 shadow-sm">
                            <div className="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">24/7</div>
                            <div className="text-sm text-gray-500 dark:text-gray-400 mt-1 font-medium">Availability</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* FAQ Section */}
            <div className="py-24 bg-gray-50 dark:bg-gray-800">
                <div className="mx-auto max-w-7xl px-6 lg:px-8">
                    <div className="mx-auto max-w-2xl">
                        <FreqAskedQuestions />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
