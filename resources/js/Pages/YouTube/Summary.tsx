import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface TranscriptItem {
    text: string;
    start: number;
    duration: number;
}

interface SummaryProps {
    auth: any;
    videoUrl: string;
    transcript: TranscriptItem[];
    hasSummary: boolean;
    includeTimestamps: boolean;
    usage: {
        used: number;
        limit: number;
        is_guest?: boolean;
    };
}

export default function Summary({ auth, videoUrl, transcript, hasSummary, includeTimestamps, usage }: SummaryProps) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        if (!transcript || transcript.length === 0) {
            alert('Transcript is empty!');
            return;
        }

        const text = transcript.map(item => {
            if (includeTimestamps) {
                const time = new Date(item.start * 1000).toISOString().substr(11, 8);
                return `[${time}] ${item.text}`;
            }
            return item.text;
        }).join('\n');

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            } else {
                throw new Error('Clipboard API unavailable');
            }
        } catch (err) {
            console.warn('Clipboard API failed, using fallback.', err);
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            textArea.style.top = "0";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    setCopied(true);
                    setTimeout(() => setCopied(false), 2000);
                } else {
                    alert('Unable to copy. Please manually select and copy.');
                }
            } catch (err) {
                console.error('Fallback copy failed', err);
                alert('Unable to copy.');
            }
            document.body.removeChild(textArea);
        }
    };

    const usagePercent = Math.min(100, (usage.used / usage.limit) * 100);

    return (
        <AuthenticatedLayout>
            <Head title="YouTube Result" />

            {/* Hero */}
            <div className="relative overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-br from-emerald-950 via-teal-950 to-slate-900" />
                <div className="absolute top-10 right-20 w-64 h-64 bg-emerald-500/20 rounded-full blur-3xl" />
                <div className="absolute bottom-0 left-10 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl" />

                <div className="relative py-10 lg:py-14">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div className="min-w-0">
                                <div className="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 backdrop-blur-sm px-3 py-1 text-xs font-medium text-emerald-300 mb-3">
                                    <svg className="w-3 h-3 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                    Analysis Complete
                                </div>
                                <h1 className="text-2xl font-bold text-white mb-1">Video Result</h1>
                                <a href={videoUrl} target="_blank" rel="noopener noreferrer" className="text-sm text-emerald-300/80 hover:text-emerald-200 truncate block max-w-lg transition-colors">
                                    {videoUrl}
                                </a>
                            </div>
                            <Link href={route('youtube.home')} className="flex items-center gap-2 text-sm font-medium text-white/70 hover:text-white bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 ring-1 ring-white/10 hover:ring-white/20 transition-all">
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Analyze Another
                            </Link>
                        </div>

                        {/* Usage Bar */}
                        <div className="mt-6 bg-white/5 backdrop-blur-sm rounded-xl border border-white/10 p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <div>
                                <span className="text-sm text-gray-300">
                                    Credits used: <strong className="text-white">{usage.used} / {usage.limit}</strong>
                                </span>
                                {usage.is_guest && (
                                    <Link href={route('register')} className="text-xs text-emerald-400 hover:text-emerald-300 underline ml-3 transition-colors">
                                        Create free account for more &rarr;
                                    </Link>
                                )}
                            </div>
                            <div className="w-full sm:w-1/3 bg-white/10 rounded-full h-2 overflow-hidden">
                                <div
                                    className={`h-2 rounded-full transition-all duration-500 ${usagePercent > 80 ? 'bg-red-500' : usagePercent > 50 ? 'bg-amber-500' : 'bg-emerald-500'}`}
                                    style={{ width: `${usagePercent}%` }}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Content */}
            <div className="py-10 bg-gray-50 dark:bg-gray-900">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                        {/* Summary Column */}
                        {hasSummary && (
                            <div className="col-span-1">
                                <div className="bg-white dark:bg-gray-800 rounded-2xl ring-1 ring-gray-200/50 dark:ring-gray-700/50 shadow-sm overflow-hidden">
                                    <div className="px-5 py-3 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 border-b border-indigo-100 dark:border-indigo-800">
                                        <div className="flex items-center gap-2">
                                            <svg className="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                            <h4 className="text-sm font-semibold text-indigo-800 dark:text-indigo-300 uppercase tracking-wider">AI Summary</h4>
                                        </div>
                                    </div>
                                    <div className="p-5">
                                        <ul className="list-disc list-outside ml-4 space-y-2 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                            <li>Video discusses core concepts of Laravel.</li>
                                            <li>Mentions multi-tenancy architecture.</li>
                                            <li>Comparison between free and paid tiers.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Transcript Column */}
                        <div className={hasSummary ? "col-span-2" : "col-span-3"}>
                            <div className="bg-white dark:bg-gray-800 rounded-2xl ring-1 ring-gray-300 dark:ring-gray-700/50 shadow-sm overflow-hidden">
                                <div className="px-5 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                                    <div className="flex items-center gap-2">
                                        <svg className="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <h4 className="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Transcript</h4>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleCopy}
                                        className="flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 bg-white dark:bg-gray-800 rounded-lg px-3 py-1.5 ring-1 ring-gray-200 dark:ring-gray-600 hover:ring-indigo-300 dark:hover:ring-indigo-600 transition-all"
                                    >
                                        {copied ? (
                                            <>
                                                <svg className="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span className="text-green-600">Copied!</span>
                                            </>
                                        ) : (
                                            <>
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                                </svg>
                                                Copy
                                            </>
                                        )}
                                    </button>
                                </div>
                                <div className="h-96 overflow-y-auto p-5">
                                    {transcript.map((item, index) => (
                                        <div key={index} className="mb-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2.5 rounded-lg transition-colors group">
                                            {includeTimestamps && (
                                                <span className="text-xs font-mono text-indigo-700 dark:text-indigo-400 font-semibold block mb-1">
                                                    {new Date(item.start * 1000).toISOString().substr(11, 8)}
                                                </span>
                                            )}
                                            <p className="text-sm text-gray-900 dark:text-gray-100 leading-relaxed">
                                                {item.text}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
