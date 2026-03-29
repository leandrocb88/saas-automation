import { Link } from '@inertiajs/react';

interface LinkProp {
    url: string | null;
    label: string;
    active: boolean;
}

interface MetaProp {
    current_page: number;
    from: number;
    to: number;
    total: number;
}

interface PaginationProps {
    links: LinkProp[];
    meta?: MetaProp;
    className?: string;
}

export default function Pagination({ links, meta, className = '' }: PaginationProps) {
    // Only show if there's more than one page (Laravel returns > 3 links: prev, 1, next)
    if (!links || links.length <= 3) return null;

    // Helper to clean up Laravel labels
    const cleanLabel = (label: string) => {
        return label
            .replace('&laquo; Previous', 'Previous')
            .replace('Next &raquo;', 'Next');
    };

    return (
        <div className={`mt-10 flex flex-col sm:flex-row justify-between items-center gap-6 ${className}`}>
            {/* Pagination Summary */}
            {meta && (
                <div className="flex items-center gap-3 px-4 py-2 bg-gray-50/50 dark:bg-white/5 backdrop-blur-sm rounded-2xl border border-gray-100 dark:border-white/5 transition-all animate-in fade-in slide-in-from-left-4 duration-500">
                    <div className="h-2 w-2 rounded-full bg-indigo-500 shadow-[0_0_8px_rgba(99,102,241,0.6)] animate-pulse" />
                    <span className="text-[11px] font-black uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">
                        Showing <span className="text-gray-900 dark:text-white mx-0.5">{meta.from}</span>
                        — <span className="text-gray-900 dark:text-white mx-0.5">{meta.to}</span>
                        of <span className="text-gray-900 dark:text-white mx-0.5">{meta.total}</span>
                    </span>
                </div>
            )}

            {/* Pagination Buttons */}
            <nav className="flex flex-wrap items-center justify-center gap-2 p-1.5 bg-white/50 dark:bg-gray-800/50 backdrop-blur-md rounded-[20px] border border-gray-100 dark:border-gray-700/50 shadow-sm transition-all duration-300">
                {links.map((link, key) => (
                    link.url ? (
                        <Link
                            key={key}
                            href={link.url}
                            className={`relative min-w-[44px] h-[44px] flex items-center justify-center px-4 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-300 group ${
                                link.active
                                    ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25 ring-1 ring-indigo-500/50'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-white dark:hover:bg-gray-700 shadow-none'
                            }`}
                        >
                            <span className="relative z-10" dangerouslySetInnerHTML={{ __html: cleanLabel(link.label) }} />
                            {/* Hover highlight for inactive links */}
                            {!link.active && (
                                <div className="absolute inset-0 rounded-xl bg-indigo-500/0 group-hover:bg-indigo-500/5 transition-colors duration-300" />
                            )}
                        </Link>
                    ) : (
                        <span
                            key={key}
                            className="min-w-[44px] h-[44px] flex items-center justify-center px-4 rounded-xl text-xs font-black uppercase tracking-widest text-gray-300 dark:text-gray-600 cursor-not-allowed border border-transparent"
                            dangerouslySetInnerHTML={{ __html: cleanLabel(link.label) }}
                        />
                    )
                ))}
            </nav>
        </div>
    );
}
