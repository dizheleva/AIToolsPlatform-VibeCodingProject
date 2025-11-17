'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { 
  Users, Search, Filter, Download, Eye, CheckCircle, XCircle, 
  ChevronLeft, ChevronRight, ArrowLeft, MoreVertical, Plus, X
} from 'lucide-react';
import MainLayout from '@/components/layout/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { getRoleDisplayName, getRoleColor, formatDate } from '@/lib/utils';
import { useToast } from '@/hooks/use-toast';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  status: string;
  created_at: string;
}

interface UserData {
  id: number;
  name: string;
  email: string;
  role: string;
  status: string;
  created_at: string;
}

export default function UsersManagementPage() {
  const [user, setUser] = useState<UserData | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [statsLoading, setStatsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [roleFilter, setRoleFilter] = useState<string>('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [creating, setCreating] = useState(false);
  const [newUser, setNewUser] = useState({
    name: '',
    email: '',
    password: '',
    role: 'employee',
    status: 'approved',
  });
  const router = useRouter();
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
      loadUsers();
    } else {
      router.push('/login');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (user) {
      loadUsers();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentPage, statusFilter, roleFilter, searchQuery]);

  const loadUsers = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams({
        page: currentPage.toString(),
        per_page: '20',
      });

      if (statusFilter) params.append('status', statusFilter);
      if (roleFilter) params.append('role', roleFilter);
      if (searchQuery) params.append('search', searchQuery);

      const response = await fetch(`http://localhost:8201/api/admin/users?${params}`, {
        credentials: 'include',
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setUsers(data.data);
          setTotalPages(data.pagination.last_page);
          setTotal(data.pagination.total);
        }
      } else if (response.status === 403) {
        router.push('/dashboard');
      }
    } catch (error) {
      console.error('Error loading users:', error);
    } finally {
      setLoading(false);
      setStatsLoading(false);
    }
  };

  const handleApprove = async (userId: number, status: 'approved' | 'rejected') => {
    try {
      const response = await fetch(`http://localhost:8201/api/admin/users/${userId}/approve`, {
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
          loadUsers();
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

  const handleRoleChange = async (userId: number, newRole: string) => {
    try {
      const response = await fetch(`http://localhost:8201/api/admin/users/${userId}/role`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ role: newRole }),
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          toast({
            title: 'Успех',
            description: 'Ролята е обновена',
          });
          loadUsers();
        }
      }
    } catch (error) {
      toast({
        title: 'Грешка',
        description: 'Неуспешна промяна на роля',
        variant: 'destructive',
      });
    }
  };

  const handleExport = async () => {
    try {
      const params = new URLSearchParams();
      if (statusFilter) params.append('status', statusFilter);
      if (roleFilter) params.append('role', roleFilter);
      if (searchQuery) params.append('search', searchQuery);

      const response = await fetch(`http://localhost:8201/api/admin/users/export?${params}`, {
        credentials: 'include',
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `users_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        toast({
          title: 'Успех',
          description: 'Експортът е изтеглен успешно',
        });
      } else {
        toast({
          title: 'Грешка',
          description: 'Неуспешен експорт',
          variant: 'destructive',
        });
      }
    } catch (error) {
      toast({
        title: 'Грешка',
        description: 'Грешка при експорт',
        variant: 'destructive',
      });
    }
  };

  const handleSearch = () => {
    setCurrentPage(1);
    loadUsers();
  };

  const handleCreateUser = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreating(true);

    try {
      const response = await fetch('http://localhost:8201/api/admin/users', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(newUser),
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          toast({
            title: 'Успех',
            description: 'Потребителят е създаден успешно',
          });
          setShowCreateModal(false);
          setNewUser({
            name: '',
            email: '',
            password: '',
            role: 'employee',
            status: 'approved',
          });
          loadUsers();
        }
      } else {
        const data = await response.json();
        toast({
          title: 'Грешка',
          description: data.message || 'Неуспешно създаване на потребител',
          variant: 'destructive',
        });
      }
    } catch (error) {
      toast({
        title: 'Грешка',
        description: 'Грешка при създаване на потребител',
        variant: 'destructive',
      });
    } finally {
      setCreating(false);
    }
  };

  if (loading && users.length === 0) {
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

  return (
    <MainLayout containerSize="lg">
      {/* Header */}
      <div className="mb-6">
        <Link href="/dashboard">
          <Button variant="ghost" className="mb-4">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Назад към dashboard
          </Button>
        </Link>
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Управление на потребители</h1>
            <p className="text-gray-600">Управлявай и одобрявай потребители</p>
          </div>
          <div className="flex gap-2">
            <Button onClick={() => setShowCreateModal(true)}>
              <Plus className="mr-2 h-4 w-4" />
              Добави потребител
            </Button>
            <Button onClick={handleExport} variant="outline">
              <Download className="mr-2 h-4 w-4" />
              Експорт CSV
            </Button>
          </div>
        </div>
      </div>

      {/* Filters */}
      <Card className="mb-6">
        <CardContent className="pt-6">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="md:col-span-2">
              <div className="flex gap-2">
                <Input
                  placeholder="Търсене по име или email..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                />
                <Button onClick={handleSearch}>
                  <Search className="h-4 w-4" />
                </Button>
              </div>
            </div>
            <Select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">Всички статуси</option>
              <option value="pending">Чакащи</option>
              <option value="approved">Одобрени</option>
              <option value="rejected">Отхвърлени</option>
            </Select>
            <Select
              value={roleFilter}
              onChange={(e) => {
                setRoleFilter(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">Всички роли</option>
              <option value="backend">Backend</option>
              <option value="frontend">Frontend</option>
              <option value="qa">QA</option>
              <option value="pm">PM</option>
              <option value="designer">Designer</option>
              <option value="employee">Employee</option>
              <option value="owner">Owner</option>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">Общо потребители</p>
                <p className="text-2xl font-bold">{total}</p>
              </div>
              <Users className="h-8 w-8 text-muted-foreground" />
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">Одобрени</p>
                <p className="text-2xl font-bold text-green-600">
                  {users.filter(u => u.status === 'approved').length}
                </p>
              </div>
              <CheckCircle className="h-8 w-8 text-green-600" />
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">Чакащи одобрение</p>
                <p className="text-2xl font-bold text-amber-600">
                  {users.filter(u => u.status === 'pending').length}
                </p>
              </div>
              <XCircle className="h-8 w-8 text-amber-600" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Users Table */}
      <Card>
        <CardHeader>
          <CardTitle>Списък с потребители</CardTitle>
          <CardDescription>Общо {total} потребителя</CardDescription>
        </CardHeader>
        <CardContent>
          {users.length === 0 ? (
            <div className="text-center py-12">
              <Users className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <p className="text-muted-foreground">Няма намерени потребители</p>
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b">
                      <th className="text-left p-4 font-medium">Име</th>
                      <th className="text-left p-4 font-medium">Email</th>
                      <th className="text-left p-4 font-medium">Роля</th>
                      <th className="text-left p-4 font-medium">Статус</th>
                      <th className="text-left p-4 font-medium">Регистриран</th>
                      <th className="text-left p-4 font-medium">Действия</th>
                    </tr>
                  </thead>
                  <tbody>
                    {users.map((u) => (
                      <tr key={u.id} className="border-b hover:bg-muted/50">
                        <td className="p-4">
                          <Link 
                            href={`/admin/users/${u.id}`}
                            className="font-medium hover:text-primary"
                          >
                            {u.name}
                          </Link>
                        </td>
                        <td className="p-4 text-sm text-muted-foreground">{u.email}</td>
                        <td className="p-4">
                          <Select
                            value={u.role}
                            onChange={(e) => handleRoleChange(u.id, e.target.value)}
                            className="w-40"
                          >
                            <option value="backend">Backend</option>
                            <option value="frontend">Frontend</option>
                            <option value="qa">QA</option>
                            <option value="pm">PM</option>
                            <option value="designer">Designer</option>
                            <option value="employee">Employee</option>
                            <option value="owner">Owner</option>
                          </Select>
                        </td>
                        <td className="p-4">
                          <Badge
                            variant={u.status === 'approved' ? 'success' : u.status === 'rejected' ? 'destructive' : 'warning'}
                            style={
                              u.status === 'approved'
                                ? { backgroundColor: '#10b98120', color: '#10b981' }
                                : u.status === 'rejected'
                                ? undefined
                                : undefined
                            }
                          >
                            {u.status === 'approved' ? 'Одобрен' : u.status === 'rejected' ? 'Отхвърлен' : 'Чака одобрение'}
                          </Badge>
                        </td>
                        <td className="p-4 text-sm text-muted-foreground">
                          {formatDate(u.created_at)}
                        </td>
                        <td className="p-4">
                          <div className="flex items-center gap-2">
                            {u.status === 'pending' && (
                              <>
                                <Button
                                  size="sm"
                                  variant="outline"
                                  onClick={() => handleApprove(u.id, 'approved')}
                                  className="text-green-600 hover:text-green-700"
                                >
                                  <CheckCircle className="h-4 w-4 mr-1" />
                                  Одобри
                                </Button>
                                <Button
                                  size="sm"
                                  variant="outline"
                                  onClick={() => handleApprove(u.id, 'rejected')}
                                  className="text-red-600 hover:text-red-700"
                                >
                                  <XCircle className="h-4 w-4 mr-1" />
                                  Отхвърли
                                </Button>
                              </>
                            )}
                            {u.status === 'approved' && (
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleApprove(u.id, 'rejected')}
                                className="text-red-600 hover:text-red-700"
                              >
                                <XCircle className="h-4 w-4 mr-1" />
                                Отхвърли
                              </Button>
                            )}
                            {u.status === 'rejected' && (
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleApprove(u.id, 'approved')}
                                className="text-green-600 hover:text-green-700"
                              >
                                <CheckCircle className="h-4 w-4 mr-1" />
                                Одобри
                              </Button>
                            )}
                            <Link href={`/admin/users/${u.id}`}>
                              <Button size="sm" variant="ghost">
                                <Eye className="h-4 w-4" />
                              </Button>
                            </Link>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className="border-t pt-4 mt-4">
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                      Показване на {((currentPage - 1) * 20) + 1}-{Math.min(currentPage * 20, total)} от {total} потребителя
                    </p>
                    <div className="flex items-center gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                        disabled={currentPage === 1}
                      >
                        <ChevronLeft className="h-4 w-4" />
                        Предишна
                      </Button>
                      <div className="flex items-center gap-1">
                        {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                          let pageNum;
                          if (totalPages <= 5) {
                            pageNum = i + 1;
                          } else if (currentPage <= 3) {
                            pageNum = i + 1;
                          } else if (currentPage >= totalPages - 2) {
                            pageNum = totalPages - 4 + i;
                          } else {
                            pageNum = currentPage - 2 + i;
                          }
                          
                          return (
                            <Button
                              key={pageNum}
                              variant={currentPage === pageNum ? 'default' : 'outline'}
                              size="sm"
                              onClick={() => setCurrentPage(pageNum)}
                              className="min-w-[40px]"
                            >
                              {pageNum}
                            </Button>
                          );
                        })}
                      </div>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                        disabled={currentPage === totalPages}
                      >
                        Следваща
                        <ChevronRight className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>

      {/* Create User Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <Card className="w-full max-w-md">
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>Добави нов потребител</CardTitle>
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={() => {
                    setShowCreateModal(false);
                    setNewUser({
                      name: '',
                      email: '',
                      password: '',
                      role: 'employee',
                      status: 'approved',
                    });
                  }}
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
              <CardDescription>
                Създай нов потребителски акаунт
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleCreateUser} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Име *</Label>
                  <Input
                    id="name"
                    required
                    value={newUser.name}
                    onChange={(e) => setNewUser({ ...newUser, name: e.target.value })}
                    placeholder="Иван Иванов"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="email">Email *</Label>
                  <Input
                    id="email"
                    type="email"
                    required
                    value={newUser.email}
                    onChange={(e) => setNewUser({ ...newUser, email: e.target.value })}
                    placeholder="ivan@example.com"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="password">Парола *</Label>
                  <Input
                    id="password"
                    type="password"
                    required
                    minLength={8}
                    value={newUser.password}
                    onChange={(e) => setNewUser({ ...newUser, password: e.target.value })}
                    placeholder="Минимум 8 символа"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="role">Роля *</Label>
                  <Select
                    id="role"
                    required
                    value={newUser.role}
                    onChange={(e) => setNewUser({ ...newUser, role: e.target.value })}
                  >
                    <option value="employee">Employee</option>
                    <option value="backend">Backend Developer</option>
                    <option value="frontend">Frontend Developer</option>
                    <option value="qa">QA Engineer</option>
                    <option value="pm">Project Manager</option>
                    <option value="designer">Designer</option>
                    <option value="owner">Owner</option>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="status">Статус *</Label>
                  <Select
                    id="status"
                    required
                    value={newUser.status}
                    onChange={(e) => setNewUser({ ...newUser, status: e.target.value })}
                  >
                    <option value="approved">Одобрен</option>
                    <option value="pending">Чака одобрение</option>
                    <option value="rejected">Отхвърлен</option>
                  </Select>
                </div>

                <div className="flex gap-2 pt-4">
                  <Button
                    type="button"
                    variant="outline"
                    className="flex-1"
                    onClick={() => {
                      setShowCreateModal(false);
                      setNewUser({
                        name: '',
                        email: '',
                        password: '',
                        role: 'employee',
                        status: 'approved',
                      });
                    }}
                  >
                    Отказ
                  </Button>
                  <Button type="submit" className="flex-1" disabled={creating}>
                    {creating ? 'Създаване...' : 'Създай потребител'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      )}
    </MainLayout>
  );
}

