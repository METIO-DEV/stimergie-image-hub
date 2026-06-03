import AppFooter from "@/Components/AppFooter";
import { PropsWithChildren } from "react";

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col bg-[#264653] text-[#080506]">
            <div className="flex flex-1 items-center justify-center px-5 py-10">
                <div className="flex w-full max-w-[520px] translate-y-6 flex-col items-center">
                    <div className="mb-14 flex justify-center">
                        <img
                            src="/logo_stimergie_baseline_login.jpg"
                            alt="Stimergie"
                            className="h-auto w-[260px] sm:w-[300px]"
                        />
                    </div>

                    <div className="w-full rounded-xl bg-[#f7f8f8] px-9 py-10 shadow-[0_24px_60px_rgba(0,0,0,0.16)] sm:px-10">
                        {children}
                    </div>
                </div>
            </div>
            <AppFooter />
        </div>
    );
}
