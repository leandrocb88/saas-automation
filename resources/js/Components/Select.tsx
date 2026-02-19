import { Listbox, Transition } from '@headlessui/react';
import { Fragment } from 'react';

export interface SelectOption {
    value: string;
    label: string;
}

interface SelectProps {
    label?: string;
    options: SelectOption[];
    value: string;
    onChange: (value: string) => void;
    className?: string;
    minWidth?: string;
}

export default function Select({
    label,
    options,
    value,
    onChange,
    className = '',
    minWidth = '200px',
}: SelectProps) {
    const selectedOption = options.find((opt) => opt.value === value) || options[0];

    return (
        <Listbox value={value} onChange={onChange}>
            <div className={`relative ${className}`} style={{ minWidth }}>
                {label && (
                    <Listbox.Label className="absolute left-5 top-2.5 text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-[0.15em] z-10 pointer-events-none">
                        {label}
                    </Listbox.Label>
                )}
                <Listbox.Button className="relative w-full h-[52px] pl-5 pr-10 pt-4 pb-1 text-left bg-white/50 dark:bg-white/10 backdrop-blur-sm border border-gray-200 dark:border-white/10 rounded-2xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/50 transition-all hover:border-gray-300 dark:hover:border-white/20">
                    <span className="block truncate text-[13px] font-bold text-gray-900 dark:text-white">
                        {selectedOption.label}
                    </span>
                    <span className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4">
                        <svg
                            className="h-4 w-4 text-gray-400 group-focus-within:text-indigo-500 transition-colors"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        >
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </Listbox.Button>
                <Transition
                    as={Fragment}
                    leave="transition ease-in duration-100"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <Listbox.Options className="absolute z-50 mt-2 max-h-60 w-full overflow-auto rounded-2xl bg-white/80 dark:bg-gray-900/90 backdrop-blur-xl py-2 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm border border-gray-200/50 dark:border-white/10">
                        {options.map((option) => (
                            <Listbox.Option
                                key={option.value}
                                className={({ active, selected }) =>
                                    `relative cursor-pointer select-none py-3 px-5 transition-colors ${active ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-gray-900 dark:text-gray-300'
                                    } ${selected ? 'font-bold' : 'font-medium'}`
                                }
                                value={option.value}
                            >
                                {({ selected }) => (
                                    <div className="flex items-center justify-between">
                                        <span className={`block truncate ${selected ? 'text-indigo-600 dark:text-indigo-400' : ''}`}>
                                            {option.label}
                                        </span>
                                        {selected && (
                                            <span className="flex items-center text-indigo-600 dark:text-indigo-400">
                                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                            </span>
                                        )}
                                    </div>
                                )}
                            </Listbox.Option>
                        ))}
                    </Listbox.Options>
                </Transition>
            </div>
        </Listbox>
    );
}
