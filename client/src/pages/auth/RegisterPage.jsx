export default function RegisterPage() {
    return (
        <>
            <div className="flex justify-center items-center h-screen">
                <div className="flex flex-col gap-2">
                    <input className="border border-gray-300 rounded-md p-2" type="text" placeholder="Nome" />
                    <input className="border border-gray-300 rounded-md p-2" type="email" placeholder="Email" />
                    <input className="border border-gray-300 rounded-md p-2" type="password" placeholder="Senha" />
                    <button className="bg-blue-500 text-white rounded-md p-2">Register</button>
                </div>
            </div>
        </>
    )
}