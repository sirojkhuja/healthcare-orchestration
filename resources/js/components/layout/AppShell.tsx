import { Outlet } from 'react-router';
import { Sidebar } from './Sidebar';
import { Header } from './Header';
import { ToastContainer } from '@/components/ui/Toast';
import { useUiStore } from '@/store/uiStore';
import { cn } from '@/lib/utils/cn';

export function AppShell() {
  const sidebarOpen = useUiStore((s) => s.sidebarOpen);

  return (
    <div className="min-h-screen bg-gray-50">
      <Sidebar />
      <Header />
      <main
        className={cn(
          'pt-14 transition-all duration-200',
          sidebarOpen ? 'ml-60' : 'ml-16',
        )}
      >
        <div className="p-6">
          <Outlet />
        </div>
      </main>
      <ToastContainer />
    </div>
  );
}
