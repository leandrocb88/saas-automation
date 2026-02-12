import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface UsageStats {
    used: number;
    limit: number;
    period: string;
    is_pro: boolean;
    plan_name: string;
    next_reset_at?: string;
}

export default function Billing({ auth, service, usageStats, subscription_ends_at }: { auth: any, service: string, usageStats: UsageStats, subscription_ends_at?: string }) {
    const usagePercent = Math.min(100, (usageStats.used / usageStats.limit) * 100);

    return (
        <AuthenticatedLayout>
            <Head title="Billing" />

            {/* Hero */}
            <div className="relative overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-br from-slate-900 via-gray-900 to-slate-900" />
                <div className="absolute top-10 right-20 w-72 h-72 bg-indigo-500/10 rounded-full blur-3xl" />
                <div className="absolute bottom-0 left-10 w-80 h-80 bg-purple-500/10 rounded-full blur-3xl" />

                <div className="relative py-10 lg:py-14">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-2xl font-bold text-white mb-1">Billing & Subscription</h1>
                                <p className="text-sm text-gray-400">Manage your plan and usage</p>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className={`text-xs font-semibold px-3 py-1.5 rounded-full backdrop-blur-sm ring-1 ${usageStats.is_pro ? 'bg-green-500/10 text-green-300 ring-green-500/20' : 'bg-gray-500/10 text-gray-300 ring-gray-500/20'}`}>
                                    {usageStats.plan_name} Plan
                                </span>
                                <span className="text-xs text-gray-400 bg-white/5 px-3 py-1.5 rounded-full ring-1 ring-white/10">
                                    {usageStats.period}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="py-10 bg-gray-50 dark:bg-gray-900">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* Grace Period Warning */}
                    {subscription_ends_at && (
                        <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-5">
                            <div className="flex items-start gap-3">
                                <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                    <svg className="h-5 w-5 text-amber-600 dark:text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                                    </svg>
                                </div>
                                <div className="flex-1">
                                    <h3 className="text-sm font-semibold text-amber-800 dark:text-amber-300">Subscription Canceled</h3>
                                    <p className="mt-1 text-sm text-amber-700 dark:text-amber-400">
                                        Your <strong>{usageStats.plan_name}</strong> plan expires on <span className="font-bold">{subscription_ends_at}</span>.
                                        After this date, your account will be downgraded to Free.
                                    </p>
                                    <a href={route('plans')} className="inline-flex items-center gap-1 mt-3 text-sm font-medium text-amber-700 dark:text-amber-300 hover:text-amber-800 dark:hover:text-amber-200 transition-colors">
                                        Resume Subscription &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Usage Card */}
                    <div className="bg-white dark:bg-gray-800 rounded-2xl ring-1 ring-gray-200/50 dark:ring-gray-700/50 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                            <h3 className="text-base font-semibold text-gray-900 dark:text-white">{service} Usage</h3>
                        </div>
                        <div className="p-6">
                            <div className="flex items-end gap-2 mb-4">
                                <span className="text-4xl font-bold text-gray-900 dark:text-white">{usageStats.used}</span>
                                <span className="text-lg text-gray-400 mb-1">/ {usageStats.limit}</span>
                                <span className="text-sm text-gray-500 mb-1 ml-1">credits</span>
                            </div>

                            <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                <div
                                    className={`h-3 rounded-full transition-all duration-1000 ${usagePercent > 80 ? 'bg-gradient-to-r from-red-500 to-orange-500' : usagePercent > 50 ? 'bg-gradient-to-r from-amber-500 to-yellow-500' : 'bg-gradient-to-r from-green-500 to-emerald-500'}`}
                                    style={{ width: `${usagePercent}%` }}
                                />
                            </div>

                            <div className="mt-3 flex justify-between items-center">
                                {usageStats.next_reset_at && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Resets on <span className="font-semibold">{usageStats.next_reset_at}</span>
                                    </p>
                                )}
                                <p className="text-xs text-gray-400 italic">Resets {usageStats.period}</p>
                            </div>

                            {!usageStats.is_pro && (
                                <div className="mt-6 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 p-4 rounded-xl ring-1 ring-indigo-200/50 dark:ring-indigo-800/50">
                                    <p className="text-sm text-indigo-700 dark:text-indigo-300">
                                        You are on the Free tier. Upgrade to <strong>Pro</strong> for higher limits.
                                    </p>
                                    <Link href="/plans" className="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 transition-colors">
                                        Upgrade to Pro &rarr;
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Manage Subscription Card */}
                    <div className="bg-white dark:bg-gray-800 rounded-2xl ring-1 ring-gray-200/50 dark:ring-gray-700/50 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 className="text-base font-semibold text-gray-900 dark:text-white">Manage Subscription</h3>
                        </div>
                        <div className="p-6">
                            {usageStats.is_pro ? (
                                <div className="space-y-4">
                                    {subscription_ends_at ? (
                                        <div className="flex items-center justify-between p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl ring-1 ring-amber-200/50 dark:ring-amber-800/50">
                                            <div>
                                                <p className="text-sm text-amber-800 dark:text-amber-300 font-medium">
                                                    Your subscription is canceling.
                                                </p>
                                                <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                                    Access continues until {subscription_ends_at}.
                                                </p>
                                            </div>
                                            <Link
                                                href={route('subscription.resume')}
                                                method="post"
                                                as="button"
                                                className="px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-sm font-medium rounded-lg shadow-lg shadow-amber-500/20 hover:shadow-amber-500/30 transition-all"
                                            >
                                                Resume
                                            </Link>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col sm:flex-row gap-3">
                                            <Link
                                                href={route('plans')}
                                                className="inline-flex justify-center items-center px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl font-semibold text-sm text-white shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-all"
                                            >
                                                Change Plan
                                            </Link>
                                            <Link
                                                href={route('subscription.cancel')}
                                                method="post"
                                                as="button"
                                                className="inline-flex justify-center items-center px-5 py-2.5 bg-white dark:bg-gray-700 rounded-xl font-semibold text-sm text-gray-700 dark:text-gray-300 ring-1 ring-gray-200 dark:ring-gray-600 hover:ring-gray-300 dark:hover:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all"
                                            >
                                                Cancel Subscription
                                            </Link>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/30 rounded-xl ring-1 ring-gray-200/50 dark:ring-gray-600/50">
                                    <div>
                                        <p className="text-sm text-gray-900 dark:text-gray-100 font-medium">
                                            You are currently on the Free Plan.
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Upgrade to remove limits and unlock all features.
                                        </p>
                                    </div>
                                    <Link
                                        href={route('plans')}
                                        className="px-5 py-2.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white text-sm font-semibold rounded-xl shadow-lg shadow-green-500/20 hover:shadow-green-500/30 transition-all"
                                    >
                                        Upgrade Now
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
