'use client';

import { useState, useEffect } from 'react';
import { useRouter, useParams } from 'next/navigation';
import Link from 'next/link';
import { 
  ArrowLeft, User, Mail, Shield, Calendar, Hash, Wrench, Heart,
  CheckCircle, XCircle, AlertCircle
} from 'lucide-react';
import MainLayout from '@/components/layout/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { getRoleDisplayName, getRoleColor, formatDate } from '@/lib/utils';
import { useToast } from '@/hooks/use-toast';

interface UserDetails {
  id: number;
  name: string;
  email: string;
  role: string;
  status: string;
  created_at: string;
  updated_at: string;
  created_tools_count: number;
  liked_tools_count: number;
  created_tools: Array<{
    id: number;
    name: string;
    slug: string;
    status: string;
    created_at: string;
  }>;
}

interface UserData {
  id: number;
  name: string;
  email: string;
  role: string;
  status: string;
}

export default function UserDetailsPage() {
  const [user, setUser] = useState<UserData | null>(null);
  const [userDetails, setUserDetails] = useState<UserDetails | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();
  const params = useParams();
  const userId = params.id as string;
  const { toast } = useToast();

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      const userData = JSON.parse(storedUser);
      setUser(userData);
      if (userData.role !== 'owner' || userData.status !== 'approved') {
        router.push('/dashboard');
        return;
      }
      loadUserDetails();
    } else {
      router.push('/login');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);

  const loadUserDetails = async () => {
    try {
      setLoading(true);
      const response = await fetch(`http://localhost:8201/api/admin/users/${userId}`, {
        credentials: 'include',
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setUserDetails(data.data);
        }
      } else if (response.status === 403) {
        router.push('/dashboard');
      }
    } catch (error) {
      console.error('Error loading user details:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async (status: 'approved' | 'rejected') => {
    if (!userDetails) return;

    try {
      const response = await fetch(`http://localhost:8201/api/admin/users/${userDetails.id}/approve`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ status }),
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          toast({
            title: 'Успех',
            description: status === 'approved' ? 'Потребителят е одобрен' : 'Потребителят е отхвърлен',
          });
          loadUserDetails();
        }
      }
    } catch (error) {
      toast({
        title: 'Грешка',
        description: 'Неуспешно одобряване/отхвърляне',
        variant: 'destructive',
      });
    }
  };

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

  if (!userDetails) {
    return (
      <MainLayout>
        <div className="text-center py-12">
          <p className="text-muted-foreground">Потребителят не е намерен</p>
          <Link href="/admin/users">
            <Button variant="outline" className="mt-4">
              Назад към потребители
            </Button>
          </Link>
        </div>
      </MainLayout>
    );
  }

  const isApproved = userDetails.status === 'approved';

  return (
    <MainLayout containerSize="lg">
      {/* Header */}
      <div className="mb-6">
        <Link href="/admin/users">
          <Button variant="ghost" className="mb-4">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Назад към потребители
          </Button>
        </Link>
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 mb-2">{userDetails.name}</h1>
            <p className="text-gray-600">Детайли за потребителя</p>
          </div>
          {userDetails.status === 'pending' && (
            <div className="flex gap-2">
              <Button
                onClick={() => handleApprove('approved')}
                className="bg-green-600 hover:bg-green-700"
              >
                <CheckCircle className="mr-2 h-4 w-4" />
                Одобри
              </Button>
              <Button
                variant="destructive"
                onClick={() => handleApprove('rejected')}
              >
                <XCircle className="mr-2 h-4 w-4" />
                Отхвърли
              </Button>
            </div>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Info */}
        <div className="lg:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Лична информация</CardTitle>
              <CardDescription>Основна информация за потребителя</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex items-center justify-between py-3 border-b">
                <div className="flex items-center space-x-3">
                  <User className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Име</p>
                    <p className="text-lg font-semibold text-gray-900">{userDetails.name}</p>
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-between py-3 border-b">
                <div className="flex items-center space-x-3">
                  <Mail className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Email</p>
                    <p className="text-lg font-semibold text-gray-900">{userDetails.email}</p>
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
                            ? { backgroundColor: getRoleColor(userDetails.role) + '20', color: getRoleColor(userDetails.role) }
                            : undefined
                        }
                      >
                        {getRoleDisplayName(userDetails.role)}
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
                      {formatDate(userDetails.created_at)}
                    </p>
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-between py-3">
                <div className="flex items-center space-x-3">
                  <Hash className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Потребителски ID</p>
                    <p className="text-lg font-semibold text-gray-900">#{userDetails.id}</p>
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
                  <CardTitle className="text-amber-900">
                    {userDetails.status === 'pending' ? 'Чака одобрение' : 'Отхвърлен'}
                  </CardTitle>
                </div>
                <CardDescription className="text-amber-700">
                  {userDetails.status === 'pending'
                    ? 'Потребителят чака одобрение от администратор.'
                    : 'Потребителят е отхвърлен.'}
                </CardDescription>
              </CardHeader>
            </Card>
          )}

          {/* Created Tools */}
          {userDetails.created_tools.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>Създадени инструменти</CardTitle>
                <CardDescription>Последните {userDetails.created_tools.length} инструмента</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {userDetails.created_tools.map((tool) => (
                    <Link
                      key={tool.id}
                      href={`/tools/${tool.slug}`}
                      className="flex items-center justify-between p-3 border rounded-md hover:bg-muted/50 transition-colors"
                    >
                      <div>
                        <p className="font-medium">{tool.name}</p>
                        <p className="text-sm text-muted-foreground">
                          {formatDate(tool.created_at)}
                        </p>
                      </div>
                      <Badge variant={tool.status === 'active' ? 'success' : 'warning'}>
                        {tool.status}
                      </Badge>
                    </Link>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Stats */}
          <Card>
            <CardHeader>
              <CardTitle>Статистика</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Wrench className="h-5 w-5 text-muted-foreground" />
                  <span className="text-sm text-muted-foreground">Създадени инструменти</span>
                </div>
                <span className="text-lg font-semibold">{userDetails.created_tools_count}</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Heart className="h-5 w-5 text-muted-foreground" />
                  <span className="text-sm text-muted-foreground">Харесани инструменти</span>
                </div>
                <span className="text-lg font-semibold">{userDetails.liked_tools_count}</span>
              </div>
            </CardContent>
          </Card>

          {/* Status Card */}
          <Card>
            <CardHeader>
              <CardTitle>Статус</CardTitle>
            </CardHeader>
            <CardContent>
              <Badge
                variant={isApproved ? 'success' : userDetails.status === 'rejected' ? 'destructive' : 'warning'}
                className="text-sm px-4 py-2"
                style={
                  isApproved
                    ? { backgroundColor: '#10b98120', color: '#10b981' }
                    : undefined
                }
              >
                {isApproved ? 'Одобрен' : userDetails.status === 'rejected' ? 'Отхвърлен' : 'Чака одобрение'}
              </Badge>
            </CardContent>
          </Card>
        </div>
      </div>
    </MainLayout>
  );
}

