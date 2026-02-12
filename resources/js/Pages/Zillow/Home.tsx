import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ZillowHome({ auth }: { auth: any }) {
    const { data, setData, post, processing, errors } = useForm({
        address_or_url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('zillow.process'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Zillow Analyzer
                </h2>
            }
        >
            <Head title="Real Estate Analyzer" />

            <div className="py-20 lg:py-32 bg-gradient-to-br from-white to-blue-50 dark:from-gray-900 dark:to-blue-900/20">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-3xl mx-auto">
                        <div className="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-sm font-medium text-blue-800 dark:border-blue-900/30 dark:bg-blue-900/20 dark:text-blue-300 mb-6">
                            <span className="flex h-2 w-2 rounded-full bg-blue-600 mr-2 animate-pulse"></span>
                            AI Property Insights
                        </div>
                        <h1 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl dark:text-white mb-6">
                            Analyze Any Property in <span className="text-blue-600">Seconds</span>.
                        </h1>
                        <p className="mt-4 text-xl text-gray-600 dark:text-gray-400 mb-10">
                            Enter an address or Zillow URL to get instant rental estimates, cash flow analysis, and AI investment advice.
                        </p>

                        <form onSubmit={submit} className="max-w-xl mx-auto">
                            <div className="relative flex items-center mb-4">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    name="address_or_url"
                                    id="address_or_url"
                                    className="block w-full rounded-full border-gray-300 pl-10 pr-32 py-4 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                                    placeholder="Enter address or Zillow URL..."
                                    value={data.address_or_url}
                                    onChange={(e) => setData('address_or_url', e.target.value)}
                                    required
                                />
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="absolute right-2 top-2 bottom-2 rounded-full bg-blue-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50"
                                >
                                    {processing ? 'Analyzing...' : 'Analyze'}
                                </button>
                            </div>

                            {errors.address_or_url && <p className="mt-2 text-sm text-red-600 text-left pl-4">{errors.address_or_url}</p>}
                            {/* @ts-ignore */}
                            {errors.limit && <p className="mt-2 text-sm text-red-600 text-left pl-4">{errors.limit}</p>}
                        </form>

                        <div className="mt-12 flex justify-center gap-8 text-gray-400 grayscale opacity-70">
                            <div className="flex items-center gap-2">
                                <svg className="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Rental Estimates</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <svg className="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
                                </svg>
                                <span>ROI Calculation</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
