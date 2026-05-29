import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="grid min-h-screen bg-[#f4f2ee] text-[#1d2528] lg:grid-cols-[minmax(0,0.92fr)_minmax(420px,0.58fr)]">
            <div className="relative hidden overflow-hidden bg-[#254956] lg:block">
                <img
                    src="/logo_stimergie_baseline_login.jpg"
                    alt="Stimergie"
                    className="absolute left-1/2 top-1/2 w-[min(72%,620px)] -translate-x-1/2 -translate-y-1/2"
                />
            </div>

            <div className="flex min-h-screen items-center justify-center px-6 py-10">
                <div className="w-full max-w-[440px]">
                    <div className="mb-10 lg:hidden">
                        <img
                            src="/logo_stimergie_header.png"
                            alt="Stimergie"
                            className="h-auto w-56"
                        />
                    </div>

                    <div className="border border-[#d8d5cc] bg-white px-7 py-8 shadow-sm sm:px-9">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
