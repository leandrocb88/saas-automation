import React from 'react';

/**
 * Standard Glassmorphism Card Example
 * 
 * follow these patterns for all primary content containers
 * to ensure a premium, unified look.
 */
export const GlassCard = ({ title, children }: { title: string, children: React.ReactNode }) => {
    return (
        <div className="bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-[2rem] border border-gray-200/50 dark:border-gray-700/50 overflow-hidden shadow-xl shadow-gray-200/20 dark:shadow-none transition-all duration-300 hover:shadow-2xl">
            {/* Header section with subtle gradient */}
            <div className="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/10 dark:to-purple-900/10 border-b border-indigo-100 dark:border-indigo-800/30">
                <h3 className="font-black text-sm text-indigo-900 dark:text-indigo-100 uppercase tracking-widest leading-none">
                    {title}
                </h3>
            </div>
            
            {/* Main content body */}
            <div className="p-6 sm:p-8">
                {children}
            </div>
            
            {/* Optional footer/action area */}
            <div className="px-6 py-4 bg-gray-50/30 dark:bg-gray-900/30 border-t border-gray-100 dark:border-gray-800/30">
                 <button className="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 transition-colors">
                     View Details
                 </button>
            </div>
        </div>
    );
};
