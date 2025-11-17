'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Wrench, User, Users, BarChart3, TrendingUp, Eye, Heart, Plus } from 'lucide-react';
import MainLayout from '@/components/layout/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { getRoleDisplayName, getRoleColor } from '@/lib/utils';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  status: string;
  created_at: string;
}

interface DashboardStats {
  total_tools: number;
  total_views: number;
  total_likes: number;
  last_activity: string;
}

export default function DashboardPage() {
  const [user, setUser] = useState<User | null>(null);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [statsLoading, setStatsLoading] = useState(true);
  const router = useRouter();

  const fetchStats = async () => {
    try {
      setStatsLoading(true);
      const response = await fetch('http://localhost:8201/api/dashboard/stats', {
        credentials: 'include',
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setStats(data.data);
        }
      }
    } catch (error) {
      console.error('Error fetching stats:', error);
    } finally {
      setStatsLoading(false);
    }
  };

  const fetchUserData = async () => {
    try {
      const response = await fetch('http://localhost:8201/api/user', {
        credentials: 'include',
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setUser(data.user);
          localStorage.setItem('user', JSON.stringify(data.user));
          fetchStats();
        } else {
          router.push('/login');
        }
      } else {
        router.push('/login');
      }
    } catch (error) {
      router.push('/login');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      setUser(JSON.parse(storedUser));
      setLoading(false);
      fetchStats();
    } else {
      fetchUserData();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
          <p className="mt-4 text-gray-600">Зареждане...</p>
        </div>
      </div>
    );
  }

  if (!user) {
    return null;
  }

  const isApproved = user.status === 'approved';
  const canManageTools = isApproved && user.role !== 'employee';
  const isOwner = user.role === 'owner' && isApproved;

  return (
    <MainLayout>
      {/* Welcome Header */}
      <div className="mb-8">
        <div className="mb-6">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">
            Добре дошъл, {user.name}!
          </h1>
          <Badge
            variant={isApproved ? 'success' : 'warning'}
            className="text-sm px-3 py-1 mb-2"
            style={
              isApproved
                ? { backgroundColor: getRoleColor(user.role) + '20', color: getRoleColor(user.role) }
                : undefined
            }
          >
            {isApproved ? (
              <>
                <span className="mr-1">✓</span>
                {getRoleDisplayName(user.role)}
              </>
            ) : (
              <>
                {getRoleDisplayName(user.role)}
                <span className="ml-1 text-xs font-normal">⏳ Чака одобрение</span>
              </>
            )}
          </Badge>
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <p className="text-gray-600">Ето какво се случва в твоята платформа днес</p>
            {canManageTools && (
              <Link href="/tools/new">
                <Button size="lg" className="w-full sm:w-auto">
                  <Plus className="mr-2 h-5 w-5" />
                  Добави нов инструмент
                </Button>
              </Link>
            )}
          </div>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Общо инструменти</CardTitle>
            <BarChart3 className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            {statsLoading ? (
              <div className="text-2xl font-bold text-muted-foreground">...</div>
            ) : (
              <div className="text-2xl font-bold">{stats?.total_tools ?? 0}</div>
            )}
            <p className="text-xs text-muted-foreground">Всички AI инструменти</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Преглеждания</CardTitle>
            <Eye className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            {statsLoading ? (
              <div className="text-2xl font-bold text-muted-foreground">...</div>
            ) : (
              <div className="text-2xl font-bold">{stats?.total_views.toLocaleString() ?? 0}</div>
            )}
            <p className="text-xs text-muted-foreground">Общо преглеждания</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Харесвания</CardTitle>
            <Heart className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            {statsLoading ? (
              <div className="text-2xl font-bold text-muted-foreground">...</div>
            ) : (
              <div className="text-2xl font-bold">{stats?.total_likes.toLocaleString() ?? 0}</div>
            )}
            <p className="text-xs text-muted-foreground">Общо харесвания</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Активност</CardTitle>
            <TrendingUp className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            {statsLoading ? (
              <div className="text-2xl font-bold text-muted-foreground">...</div>
            ) : (
              <div className="text-2xl font-bold">{stats?.last_activity ?? 'Няма активност'}</div>
            )}
            <p className="text-xs text-muted-foreground">Последна активност</p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <Card className="mb-8">
        <CardHeader>
          <CardTitle>Бързи действия</CardTitle>
          <CardDescription>Най-често използваните функции</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {canManageTools && (
              <Link href="/tools">
                <Card className="hover:shadow-md transition-shadow cursor-pointer h-full">
                  <CardHeader>
                    <div className="flex items-center space-x-2 mb-2">
                      <Wrench className="h-5 w-5 text-primary" />
                      <CardTitle className="text-lg">AI Tools</CardTitle>
                    </div>
                    <CardDescription>
                      Прегледай и управлявай всички AI инструменти
                    </CardDescription>
                  </CardHeader>
                </Card>
              </Link>
            )}

            {isOwner && (
              <Link href="/admin/users">
                <Card className="hover:shadow-md transition-shadow cursor-pointer h-full">
                  <CardHeader>
                    <div className="flex items-center space-x-2 mb-2">
                      <Users className="h-5 w-5 text-red-500" />
                      <CardTitle className="text-lg">Управление на потребители</CardTitle>
                    </div>
                    <CardDescription>
                      Одобрявай и управлявай потребители
                    </CardDescription>
                  </CardHeader>
                </Card>
              </Link>
            )}

            <Link href="/profile">
              <Card className="hover:shadow-md transition-shadow cursor-pointer h-full">
                <CardHeader>
                  <div className="flex items-center space-x-2 mb-2">
                    <User className="h-5 w-5 text-gray-600" />
                    <CardTitle className="text-lg">Профил</CardTitle>
                  </div>
                  <CardDescription>
                    Управлявай своя профил и настройки
                  </CardDescription>
                </CardHeader>
              </Card>
            </Link>
          </div>
        </CardContent>
      </Card>

    </MainLayout>
  );
}
