'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { User, Mail, Shield, Calendar, Hash, AlertCircle, Edit } from 'lucide-react';
import MainLayout from '@/components/layout/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import EditProfileModal from '@/components/profile/EditProfileModal';
import { getRoleDisplayName, getRoleColor, formatDate } from '@/lib/utils';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  status: string;
  avatar_url?: string | null;
  created_at: string;
}

interface UserStats {
  created_tools: number;
  liked_tools: number;
}

export default function ProfilePage() {
  const [user, setUser] = useState<User | null>(null);
  const [stats, setStats] = useState<UserStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [statsLoading, setStatsLoading] = useState(true);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const router = useRouter();

  const fetchStats = async () => {
    try {
      setStatsLoading(true);
      const response = await fetch('http://localhost:8201/api/user/stats', {
        credentials: 'include',
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setStats(data.data);
        }
      }
    } catch (error) {
      console.error('Error fetching user stats:', error);
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
      <MainLayout>
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
            <p className="mt-4 text-gray-600">Зареждане...</p>
          </div>
        </div>
      </MainLayout>
    );
  }

  if (!user) {
    return null;
  }

  const isApproved = user.status === 'approved';

  return (
    <MainLayout containerSize="md">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Профил</h1>
          <p className="text-gray-600">Управлявай своя профил и настройки</p>
        </div>
        <Button
          onClick={() => setEditModalOpen(true)}
          className="flex items-center space-x-2"
        >
          <Edit className="h-4 w-4" />
          <span>Редактирай</span>
        </Button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Profile Info Card */}
        <div className="lg:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle>Лична информация</CardTitle>
                  <CardDescription>Основна информация за твоя профил</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex items-center justify-between py-3 border-b">
                <div className="flex items-center space-x-3">
                  <User className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Име</p>
                    <p className="text-lg font-semibold text-gray-900">{user.name}</p>
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-between py-3 border-b">
                <div className="flex items-center space-x-3">
                  <Mail className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Email</p>
                    <p className="text-lg font-semibold text-gray-900">{user.email}</p>
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-between py-3 border-b">
                <div className="flex items-center space-x-3">
                  <Shield className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Роля</p>
                    <div className="mt-1">
                      <Badge
                        variant={isApproved ? 'success' : 'warning'}
                        className="text-sm"
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
                    </div>
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-between py-3 border-b">
                <div className="flex items-center space-x-3">
                  <Calendar className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Регистриран на</p>
                    <p className="text-lg font-semibold text-gray-900">
                      {formatDate(user.created_at)}
                    </p>
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-between py-3">
                <div className="flex items-center space-x-3">
                  <Hash className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Потребителски ID</p>
                    <p className="text-lg font-semibold text-gray-900">#{user.id}</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Status Warning */}
          {!isApproved && (
            <Card className="border-amber-200 bg-amber-50">
              <CardHeader>
                <div className="flex items-center space-x-2">
                  <AlertCircle className="h-5 w-5 text-amber-600" />
                  <CardTitle className="text-amber-900">Чака одобрение</CardTitle>
                </div>
                <CardDescription className="text-amber-700">
                  Профилът ви чака одобрение от администратор. След одобрение ще получите пълни права като {getRoleDisplayName(user.role)}.
                </CardDescription>
              </CardHeader>
            </Card>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Avatar Card */}
          <Card>
            <CardHeader>
              <CardTitle>Аватар</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex flex-col items-center space-y-4">
                {user.avatar_url ? (
                  <img
                    src={`http://localhost:8201${user.avatar_url}`}
                    alt={`${user.name} avatar`}
                    className="h-24 w-24 rounded-full object-cover border-2 border-gray-300"
                  />
                ) : (
                  <div className="h-24 w-24 rounded-full bg-primary flex items-center justify-center text-white text-3xl font-bold border-2 border-gray-300">
                    {user.name.charAt(0).toUpperCase()}
                  </div>
                )}
                <p className="text-sm text-muted-foreground text-center">
                  Кликни на "Редактирай" за да промениш аватара
                </p>
              </div>
            </CardContent>
          </Card>

          {/* Quick Stats */}
          <Card>
            <CardHeader>
              <CardTitle>Статистика</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-sm text-muted-foreground">Добавени инструменти</span>
                {statsLoading ? (
                  <span className="text-lg font-semibold text-muted-foreground">...</span>
                ) : (
                  <span className="text-lg font-semibold">{stats?.created_tools ?? 0}</span>
                )}
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-muted-foreground">Харесани инструменти</span>
                {statsLoading ? (
                  <span className="text-lg font-semibold text-muted-foreground">...</span>
                ) : (
                  <span className="text-lg font-semibold">{stats?.liked_tools ?? 0}</span>
                )}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Edit Profile Modal */}
      <EditProfileModal
        open={editModalOpen}
        onOpenChange={setEditModalOpen}
        user={user}
        onUpdate={(updatedUser) => {
          setUser(updatedUser);
          localStorage.setItem('user', JSON.stringify(updatedUser));
        }}
      />
    </MainLayout>
  );
}
