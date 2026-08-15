export default function Welcome({ appName }) {
    return (
        <main className="min-h-screen flex items-center justify-center bg-[#0a0c10] px-6">
            <section className="max-w-xl w-full border border-[#24303e] bg-[#0f1318] p-10 text-center">
                <p className="font-mono text-xs uppercase tracking-[0.3em] text-[#22c55e]">
                    Tactical PR Screening
                </p>
                <h1 className="mt-4 font-sans text-5xl font-bold text-[#e2e8f0]">
                    {appName}
                </h1>
                <p className="mt-4 font-sans text-lg text-[#94a3b8]">
                    Triagem tática de Pull Requests do GitHub
                </p>
            </section>
        </main>
    );
}
