import { Outlet } from 'react-router';

export function AuthShell() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
      <div className="w-full max-w-md">
        <div className="mb-8 text-center">
          <h1 className="text-2xl font-bold text-blue-600">MedFlow</h1>
          <p className="mt-1 text-sm text-gray-500">Healthcare Orchestration Platform</p>
        </div>
        <div className="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
          <Outlet />
        </div>
      </div>
    </div>
  );
}
