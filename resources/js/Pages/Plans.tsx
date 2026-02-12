import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmationModal from '@/Components/ConfirmationModal';

interface Plan {
    limit: number;
    period: string;
    prices?: {
        monthly: string;
        yearly: string;
    } | null;
    features?: string[];
    price_monthly?: number;
    price_yearly?: number;
}

interface PlansProps {
    auth: any;
    service: string;
    plans: {
        free: Plan;
        plus: Plan;
        pro: Plan;
    };
    current_plan: 'free' | 'plus' | 'pro';
    current_period?: 'monthly' | 'yearly';
    on_grace_period?: boolean;
}

export default function Plans({ auth, service, plans, current_plan, current_period, on_grace_period }: PlansProps) {
    const [billing, setBilling] = useState<'monthly' | 'yearly'>(current_period || 'monthly');

    const isCurrent = (plan: string) => current_plan === plan;
    const isCurrentPeriod = current_period === billing;
    const isPaid = current_plan === 'plus' || current_plan === 'pro';

    const [confirmingCancellation, setConfirmingCancellation] = useState(false);

    const handleCancel = () => setConfirmingCancellation(true);

    const cancelSubscription = () => {
        router.post(route('subscription.cancel'), {}, {
            onFinish: () => setConfirmingCancellation(false),
        });
    };

    const handleResume = () => {
        router.post(route('subscription.resume'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Pricing" />

            {/* Hero */}
            <div className="relative overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-br from-indigo-950 via-purple-950 to-slate-900" />
                <div className="absolute top-10 left-20 w-72 h-72 bg-indigo-500/20 rounded-full blur-3xl" />
                <div className="absolute bottom-0 right-20 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl" />

                <div className="relative py-16 lg:py-20">
                    <div className="mx-auto max-w-7xl px-6 lg:px-8">
                        <div className="mx-auto max-w-4xl text-center">
                            <div className="inline-flex items-center rounded-full border border-indigo-500/30 bg-indigo-500/10 backdrop-blur-sm px-4 py-1.5 text-sm font-medium text-indigo-300 mb-6">
                                Pricing
                            </div>
                            <h1 className="text-4xl font-bold tracking-tight text-white sm:text-5xl mb-4">
                                Simple, Transparent Pricing
                            </h1>
                            <p className="mt-2 text-lg text-gray-300/80 max-w-2xl mx-auto">
                                Choose the perfect plan for your needs. Always know what you'll pay.
                            </p>
                        </div>

                        {/* Billing Toggle */}
                        <div className="mt-10 flex justify-center">
                            <div className="relative flex rounded-full bg-white/10 backdrop-blur-sm p-1 ring-1 ring-white/10">
                                <button
                                    onClick={() => setBilling('monthly')}
                                    className={`${billing === 'monthly' ? 'bg-white text-gray-900 shadow-lg' : 'text-gray-300 hover:text-white'} rounded-full py-2 px-6 text-sm font-semibold transition-all duration-200`}
                                >
                                    Monthly
                                </button>
                                <button
                                    onClick={() => setBilling('yearly')}
                                    className={`${billing === 'yearly' ? 'bg-white text-gray-900 shadow-lg' : 'text-gray-300 hover:text-white'} rounded-full py-2 px-6 text-sm font-semibold transition-all duration-200`}
                                >
                                    Yearly <span className="text-xs text-green-500 ml-1 font-bold">
                                        -{Math.round((1 - (plans.pro.price_yearly! / (plans.pro.price_monthly! * 12))) * 100)}%
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Plans Grid */}
            <div className="py-16 bg-gray-50 dark:bg-gray-900">
                <div className="mx-auto max-w-7xl px-6 lg:px-8">
                    <div className="grid w-full grid-cols-1 gap-8 lg:grid-cols-3">

                        {/* FREE */}
                        <div className="group relative rounded-2xl bg-white dark:bg-gray-800 p-8 ring-1 ring-gray-200/50 dark:ring-gray-700/50 hover:ring-indigo-500/20 hover:shadow-lg transition-all duration-300 xl:p-10">
                            <h3 className="text-lg font-bold text-gray-900 dark:text-white">Free</h3>
                            <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                Perfect for exploring and occasional use.
                            </p>
                            <p className="mt-6 flex items-baseline gap-x-1">
                                <span className="text-5xl font-bold tracking-tight text-gray-900 dark:text-white">$0</span>
                                <span className="text-sm font-medium text-gray-500">/ forever</span>
                            </p>

                            {isCurrent('free') ? (
                                <button disabled className="mt-8 block w-full rounded-xl py-3 text-center text-sm font-semibold text-white bg-green-600 cursor-not-allowed opacity-80 shadow-sm">
                                    Current Plan
                                </button>
                            ) : isPaid ? (
                                <button
                                    onClick={handleCancel}
                                    className="mt-8 block w-full rounded-xl py-3 text-center text-sm font-semibold text-red-600 ring-1 ring-inset ring-red-200 hover:ring-red-300 hover:bg-red-50 dark:ring-red-800 dark:hover:bg-red-900/20 transition-all"
                                >
                                    Downgrade
                                </button>
                            ) : (
                                <Link
                                    href={auth.user ? route('dashboard') : route('register')}
                                    className="mt-8 block rounded-xl py-3 text-center text-sm font-semibold text-indigo-600 ring-1 ring-inset ring-indigo-200 hover:ring-indigo-300 hover:bg-indigo-50 dark:ring-indigo-800 dark:hover:bg-indigo-900/20 transition-all"
                                >
                                    {auth.user ? 'Downgrade' : 'Get Started'}
                                </Link>
                            )}

                            <ul role="list" className="mt-8 space-y-3 text-sm text-gray-600 dark:text-gray-400">
                                {plans.free.features?.map((feature) => (
                                    <li key={feature} className="flex gap-x-3 items-start">
                                        <CheckIcon />
                                        {feature}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* PLUS */}
                        <div className={`group relative rounded-2xl bg-white dark:bg-gray-800 p-8 ring-1 hover:shadow-lg transition-all duration-300 xl:p-10 ${isCurrent('plus') ? 'ring-2 ring-indigo-600 shadow-lg shadow-indigo-500/10' : 'ring-gray-200/50 dark:ring-gray-700/50 hover:ring-indigo-500/20'}`}>
                            <h3 className="text-lg font-bold text-gray-900 dark:text-white">Plus</h3>
                            <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                Ideally for individual creators.
                            </p>
                            <p className="mt-6 flex items-baseline gap-x-1">
                                <span className="text-5xl font-bold tracking-tight text-gray-900 dark:text-white">
                                    ${billing === 'monthly' ? plans.plus.price_monthly : plans.plus.price_yearly}
                                </span>
                                <span className="text-sm font-medium text-gray-500">
                                    /{billing === 'monthly' ? 'mo' : 'yr'}
                                </span>
                            </p>

                            {isCurrent('plus') && isCurrentPeriod ? (
                                on_grace_period ? (
                                    <button
                                        onClick={handleResume}
                                        className="mt-8 block w-full rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 py-3 text-center text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-all"
                                    >
                                        Resume Subscription
                                    </button>
                                ) : (
                                    <button disabled className="mt-8 block w-full rounded-xl bg-green-600 py-3 text-center text-sm font-semibold text-white cursor-not-allowed opacity-80 shadow-sm">
                                        Current Plan
                                    </button>
                                )
                            ) : isCurrent('plus') ? (
                                <button
                                    type="button"
                                    className="mt-8 block w-full rounded-xl bg-indigo-50 dark:bg-indigo-900/20 py-3 text-center text-sm font-semibold text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-200 dark:ring-indigo-800 hover:ring-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-all"
                                    onClick={() => window.location.href = route('checkout.subscription', { plan: 'plus', period: billing })}
                                >
                                    Switch to {billing === 'monthly' ? 'Monthly' : 'Yearly'}
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    className="mt-8 block w-full rounded-xl bg-indigo-50 dark:bg-indigo-900/20 py-3 text-center text-sm font-semibold text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-200 dark:ring-indigo-800 hover:ring-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-all"
                                    onClick={() => window.location.href = route('checkout.subscription', { plan: 'plus', period: billing })}
                                >
                                    Subscribe to Plus
                                </button>
                            )}

                            <ul role="list" className="mt-8 space-y-3 text-sm text-gray-600 dark:text-gray-400">
                                {plans.plus.features?.map((feature) => (
                                    <li key={feature} className="flex gap-x-3 items-start">
                                        <CheckIcon />
                                        {feature}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* PRO */}
                        <div className="group relative rounded-2xl p-8 xl:p-10 bg-gradient-to-br from-gray-900 to-indigo-950 ring-1 ring-indigo-500/30 shadow-xl shadow-indigo-500/10 hover:shadow-indigo-500/20 transition-all duration-300">
                            <div className="absolute -top-3.5 right-8 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 px-4 py-1 text-xs font-bold text-white shadow-lg shadow-indigo-500/25">
                                Most Popular
                            </div>
                            <h3 className="text-lg font-bold text-white">Pro</h3>
                            <p className="mt-3 text-sm text-gray-400">
                                For power users and professionals.
                            </p>
                            <p className="mt-6 flex items-baseline gap-x-1">
                                <span className="text-5xl font-bold tracking-tight text-white">
                                    ${billing === 'monthly' ? plans.pro.price_monthly : plans.pro.price_yearly}
                                </span>
                                <span className="text-sm font-medium text-gray-400">
                                    /{billing === 'monthly' ? 'mo' : 'yr'}
                                </span>
                            </p>

                            {isCurrent('pro') && isCurrentPeriod ? (
                                on_grace_period ? (
                                    <button
                                        onClick={handleResume}
                                        className="mt-8 block w-full rounded-xl bg-gradient-to-r from-indigo-500 to-purple-500 py-3 text-center text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:from-indigo-600 hover:to-purple-600 transition-all"
                                    >
                                        Resume Subscription
                                    </button>
                                ) : (
                                    <button disabled className="mt-8 block w-full rounded-xl bg-green-500 py-3 text-center text-sm font-semibold text-white cursor-not-allowed shadow-sm">
                                        Current Plan
                                    </button>
                                )
                            ) : isCurrent('pro') ? (
                                <button
                                    type="button"
                                    className="mt-8 block w-full rounded-xl bg-gradient-to-r from-indigo-500 to-purple-500 py-3 text-center text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:from-indigo-600 hover:to-purple-600 transition-all"
                                    onClick={() => window.location.href = route('checkout.subscription', { plan: 'pro', period: billing })}
                                >
                                    Switch to {billing === 'monthly' ? 'Monthly' : 'Yearly'}
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    className="mt-8 block w-full rounded-xl bg-gradient-to-r from-indigo-500 to-purple-500 py-3 text-center text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:from-indigo-600 hover:to-purple-600 transition-all"
                                    onClick={() => window.location.href = route('checkout.subscription', { plan: 'pro', period: billing })}
                                >
                                    Upgrade to Pro
                                </button>
                            )}
                            <ul role="list" className="mt-8 space-y-3 text-sm text-gray-300">
                                {plans.pro.features?.map((feature) => (
                                    <li key={feature} className="flex gap-x-3 items-start">
                                        <CheckIcon className="text-indigo-400" />
                                        {feature}
                                    </li>
                                ))}
                            </ul>
                        </div>

                    </div>
                </div>
            </div>

            <ConfirmationModal
                show={confirmingCancellation}
                title="Cancel Subscription"
                content="Are you sure you want to cancel your subscription? You will lose access to premium features at the end of your billing period."
                confirmText="Cancel Subscription"
                onClose={() => setConfirmingCancellation(false)}
                onConfirm={cancelSubscription}
            />
        </AuthenticatedLayout>
    );
}

function CheckIcon({ className = "text-indigo-600" }) {
    return (
        <svg className={`h-5 w-5 flex-none mt-0.5 ${className}`} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fillRule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clipRule="evenodd" />
        </svg>
    );
}
