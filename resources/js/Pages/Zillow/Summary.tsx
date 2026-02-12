import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface ZillowSummaryProps {
    auth: any;
    searchQuery: string;
    property: {
        address: string;
        price: string;
        estimate: string;
        rent_estimate: string;
        details: {
            bedrooms: number;
            bathrooms: number;
            sqft: number;
            year_built: number;
        }
    };
    usage: {
        used: number;
        limit: number;
        is_guest?: boolean;
    };
}

export default function ZillowSummary({ auth, searchQuery, property, usage }: ZillowSummaryProps) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Property Analysis
                </h2>
            }
        >
            <Head title="Property Analysis" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg dark:bg-gray-800 p-6">

                        <div className="mb-6 flex justify-between items-center">
                            <h3 className="text-lg font-bold text-gray-900 dark:text-gray-100">
                                Result for: <span className="text-blue-600">{property.address}</span>
                            </h3>
                            <Link href={route('zillow.home')} className="text-sm text-gray-500 hover:text-blue-600">
                                &larr; Analyze Another Property
                            </Link>
                        </div>

                        {/* Usage Banner */}
                        <div className="mb-8 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg flex justify-between items-center">
                            <div>
                                <span className="text-sm text-gray-600 dark:text-gray-300 block">
                                    You have used <strong>{usage.used} / {usage.limit}</strong> credits today.
                                </span>
                                {usage.is_guest && (
                                    <Link href={route('register')} className="text-xs text-blue-600 hover:text-blue-500 underline mt-1 block">
                                        Create a free account for more credits &rarr;
                                    </Link>
                                )}
                            </div>
                            <div className="w-1/3 bg-gray-200 rounded-full h-2 dark:bg-gray-600">
                                <div
                                    className="bg-blue-600 h-2 rounded-full"
                                    style={{ width: `${Math.min(100, (usage.used / usage.limit) * 100)}%` }}
                                ></div>
                            </div>
                        </div>

                        {/* Property Details Grid */}
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                            <div className="p-4 bg-blue-50 dark:bg-blue-900/10 rounded-lg border border-blue-100 dark:border-blue-800 text-center">
                                <span className="block text-sm text-gray-500 dark:text-gray-400">List Price</span>
                                <span className="block text-2xl font-bold text-gray-900 dark:text-white">{property.price}</span>
                            </div>
                            <div className="p-4 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-100 dark:border-green-800 text-center">
                                <span className="block text-sm text-gray-500 dark:text-gray-400">Zestimate</span>
                                <span className="block text-2xl font-bold text-green-700 dark:text-green-400">{property.estimate}</span>
                            </div>
                            <div className="p-4 bg-purple-50 dark:bg-purple-900/10 rounded-lg border border-purple-100 dark:border-purple-800 text-center">
                                <span className="block text-sm text-gray-500 dark:text-gray-400">Rent Estimate</span>
                                <span className="block text-2xl font-bold text-purple-700 dark:text-purple-400">{property.rent_estimate}</span>
                            </div>
                            <div className="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-100 dark:border-gray-600 text-center">
                                <span className="block text-sm text-gray-500 dark:text-gray-400">Specs</span>
                                <div className="text-sm font-semibold text-gray-800 dark:text-gray-200 mt-1">
                                    {property.details.bedrooms}bd | {property.details.bathrooms}ba | {property.details.sqft} sqft
                                </div>
                            </div>
                        </div>

                        {/* AI Analysis Mockup */}
                        <div className="border-t border-gray-200 dark:border-gray-700 pt-8">
                            <h4 className="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                                <span className="text-blue-600">AI</span> Investment Analysis
                            </h4>
                            <div className="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                <p>
                                    Based on the list price of <strong>{property.price}</strong> and estimated rent of <strong>{property.rent_estimate}</strong>,
                                    this property shows potential for positive cash flow if managed correctly. The Zestimate suggests the property might be slightly
                                    undervalued, offering immediate equity.
                                </p>
                                <p className="mt-4">
                                    <strong>Recommendation:</strong> Consider for long-term hold or rental portfolio. Verify local vacancy rates.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
