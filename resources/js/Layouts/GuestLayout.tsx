import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen w-full">
            {/* Left Column - Branding (Hidden on mobile) */}
            <div className="hidden lg:flex lg:w-1/2 relative bg-gray-900 overflow-hidden shrink-0">
                <div className="absolute inset-0 bg-gradient-to-br from-indigo-900 via-gray-900 to-black opacity-90"></div>

                {/* Abstract Shapes */}
                <div className="absolute top-0 left-0 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-indigo-600/30 rounded-full blur-3xl"></div>
                <div className="absolute bottom-0 right-0 translate-x-1/3 translate-y-1/3 w-[600px] h-[600px] bg-purple-600/20 rounded-full blur-3xl"></div>

                <div className="relative z-10 w-full h-full flex flex-col justify-between p-16 text-white">
                    <div>
                        <Link href="/">
                            <ApplicationLogo className="h-10 w-auto fill-current text-white" />
                        </Link>
                    </div>

                    <div className="mb-24">
                        <h2 className="text-5xl font-extrabold tracking-tight mb-6 leading-tight">
                            Automate your <br />
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">
                                video research.
                            </span>
                        </h2>
                        <p className="text-xl text-indigo-100/80 max-w-lg leading-relaxed">
                            Turn hours of watching into minutes of reading.
                            Summarize, analyze, and track your favorite channels with ease.
                        </p>
                    </div>

                    <div className="flex items-center gap-4 text-sm text-indigo-200/60 font-medium">
                        <span>© 2026 SaaS Automation</span>
                        <span className="w-1 h-1 bg-indigo-500 rounded-full"></span>
                        <span className="hover:text-white transition-colors cursor-pointer">Privacy</span>
                        <span className="w-1 h-1 bg-indigo-500 rounded-full"></span>
                        <span className="hover:text-white transition-colors cursor-pointer">Terms</span>
                    </div>
                </div>
            </div>

            {/* Right Column - Form */}
            <div className="w-full lg:w-1/2 flex flex-col justify-center items-center bg-gray-50 dark:bg-gray-950 p-6 sm:p-12 md:p-24 transition-colors">
                <div className="w-full max-w-md space-y-8">
                    {/* Mobile Logo */}
                    <div className="lg:hidden text-center mb-8">
                        <Link href="/" className="inline-block p-2 bg-white dark:bg-gray-800 rounded-xl shadow-lg">
                            <ApplicationLogo className="h-12 w-12 fill-current text-indigo-600 dark:text-indigo-400" />
                        </Link>
                    </div>

                    {/* Content Container */}
                    <div className="bg-white dark:bg-gray-900 p-8 sm:p-10 rounded-2xl shadow-xl shadow-gray-200/50 dark:shadow-none border border-gray-100 dark:border-gray-800 relative overflow-hidden">
                        {/* Subtle top accent */}
                        <div className="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-indigo-500 to-purple-500"></div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
