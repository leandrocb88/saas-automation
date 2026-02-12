import { Link } from '@inertiajs/react';

export default function FreqAskedQuestions() {
    return (
        <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h3 className="text-4xl font-bold text-gray-900 dark:text-white mb-6 text-center">Frequently Asked Questions</h3>
            <div className="divide-y divide-gray-900/10 dark:divide-gray-700">
                {/* General Questions */}
                <details className="group py-6 first:pt-0">
                    <summary className="flex w-full cursor-pointer items-start justify-between text-left text-gray-900 dark:text-white">
                        <span className="text-base font-semibold leading-7">How does the YouTube Summarizer work?</span>
                        <span className="ml-6 flex h-7 items-center">
                            <svg className="h-6 w-6 transform group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </summary>
                    <p className="mt-2 pr-12 text-base leading-7 text-gray-600 dark:text-gray-400">
                        We fetch the transcript of the video and use advanced AI to analyze and summarize the content into key points, actionable insights, and a detailed summary.
                    </p>
                </details>

                <details className="group py-6">
                    <summary className="flex w-full cursor-pointer items-start justify-between text-left text-gray-900 dark:text-white">
                        <span className="text-base font-semibold leading-7">Can I summarize private videos?</span>
                        <span className="ml-6 flex h-7 items-center">
                            <svg className="h-6 w-6 transform group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </summary>
                    <p className="mt-2 pr-12 text-base leading-7 text-gray-600 dark:text-gray-400">
                        Not directly. The video must be Public or Unlisted. We cannot access videos that require a YouTube login to view.
                    </p>
                </details>

                <details className="group py-6">
                    <summary className="flex w-full cursor-pointer items-start justify-between text-left text-gray-900 dark:text-white">
                        <span className="text-base font-semibold leading-7">What is the difference between Free and Pro?</span>
                        <span className="ml-6 flex h-7 items-center">
                            <svg className="h-6 w-6 transform group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </summary>
                    <p className="mt-2 pr-12 text-base leading-7 text-gray-600 dark:text-gray-400">
                        Free users get limited daily credits and 1-day history retention. Pro users unlock higher credit limits, batch processing (up to 100 videos), channel analysis, and 30-day history retention.
                    </p>
                </details>

                {/* Channel Analysis Questions */}
                <details className="group py-6">
                    <summary className="flex w-full cursor-pointer items-start justify-between text-left text-gray-900 dark:text-white">
                        <span className="text-base font-semibold leading-7">Why is there a limit of 10 channels and 100 videos?</span>
                        <span className="ml-6 flex h-7 items-center">
                            <svg className="h-6 w-6 transform group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </summary>
                    <p className="mt-2 pr-12 text-base leading-7 text-gray-600 dark:text-gray-400">
                        To ensure fast processing times and system stability, we limit each batch analysis to a maximum of 10 channels and 100 videos per channel. This prevents timeouts and ensures you get your results quickly.
                    </p>
                </details>

                <details className="group py-6">
                    <summary className="flex w-full cursor-pointer items-start justify-between text-left text-gray-900 dark:text-white">
                        <span className="text-base font-semibold leading-7">Do duplicates consume credits?</span>
                        <span className="ml-6 flex h-7 items-center">
                            <svg className="h-6 w-6 transform group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </summary>
                    <p className="mt-2 pr-12 text-base leading-7 text-gray-600 dark:text-gray-400">
                        Yes. Every time you run an analysis, our system fetches fresh data from YouTube and processes it using our AI engine. Even if a video was analyzed previously, a new analysis incurs API costs, so it consumes credits.
                    </p>
                </details>

                <details className="group py-6">
                    <summary className="flex w-full cursor-pointer items-start justify-between text-left text-gray-900 dark:text-white">
                        <span className="text-base font-semibold leading-7">How are credits calculated?</span>
                        <span className="ml-6 flex h-7 items-center">
                            <svg className="h-6 w-6 transform group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </summary>
                    <p className="mt-2 pr-12 text-base leading-7 text-gray-600 dark:text-gray-400">
                        Credits are frozen upfront based on the estimated maximum cost (Channels × Max Videos). After the analysis completes, any unused credits (for videos that weren't found or couldn't be processed) are automatically refunded to your account.
                    </p>
                </details>

                <details className="group py-6 last:pb-0">
                    <summary className="flex w-full cursor-pointer items-start justify-between text-left text-gray-900 dark:text-white">
                        <span className="text-base font-semibold leading-7">What if the analysis takes a long time?</span>
                        <span className="ml-6 flex h-7 items-center">
                            <svg className="h-6 w-6 transform group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </summary>
                    <p className="mt-2 pr-12 text-base leading-7 text-gray-600 dark:text-gray-400">
                        Analyzing hundreds of videos can take several minutes. If your browser times out, the analysis continues running in the background. Simply check the <Link href={route('youtube.history')} className="text-indigo-600 hover:text-indigo-500 underline">History page</Link> in a few minutes to see your results.
                    </p>
                </details>
            </div>
        </div>
    );
}
