'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { AlertCircle, Info } from 'lucide-react';

export default function RegisterPage() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: ''
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const router = useRouter();

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    // Validate password match
    if (formData.password !== formData.password_confirmation) {
      setError('Паролите не съвпадат');
      setLoading(false);
      return;
    }

    try {
      const response = await fetch('http://localhost:8201/api/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(formData),
      });

      const data = await response.json();

      if (data.success) {
        // Store user data in localStorage for client-side use
        localStorage.setItem('user', JSON.stringify(data.user));
        router.push('/dashboard');
      } else {
        setError(data.message || 'Регистрацията неуспешна');
      }
    } catch (err) {
      setError('Грешка в мрежата. Моля, опитайте отново.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-gray-900 mb-2">
            AI Tools Platform
          </h1>
          <p className="text-gray-600">
            Вътрешна платформа за споделяне на AI инструменти
          </p>
        </div>

        <Card className="shadow-lg">
          <CardHeader className="space-y-1">
            <CardTitle className="text-2xl text-center">Създаване на акаунт</CardTitle>
            <CardDescription className="text-center">
              Попълнете данните си за регистрация
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={handleSubmit}>
              <div className="space-y-2">
                <Label htmlFor="name">Пълно име</Label>
                <Input
                  id="name"
                  name="name"
                  type="text"
                  required
                  placeholder="Иван Иванов"
                  value={formData.name}
                  onChange={handleChange}
                  disabled={loading}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="email">Имейл адрес</Label>
                <Input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  placeholder="name@example.com"
                  value={formData.email}
                  onChange={handleChange}
                  disabled={loading}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="role">Роля</Label>
                <Select
                  id="role"
                  name="role"
                  required
                  value={formData.role}
                  onChange={handleChange}
                  disabled={loading}
                >
                  <option value="">Изберете вашата роля</option>
                  <option value="backend">Backend Developer</option>
                  <option value="frontend">Frontend Developer</option>
                  <option value="qa">QA Engineer</option>
                  <option value="pm">Project Manager</option>
                  <option value="designer">Designer</option>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="password">Парола</Label>
                <Input
                  id="password"
                  name="password"
                  type="password"
                  required
                  placeholder="••••••••"
                  value={formData.password}
                  onChange={handleChange}
                  disabled={loading}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="password_confirmation">Потвърди парола</Label>
                <Input
                  id="password_confirmation"
                  name="password_confirmation"
                  type="password"
                  required
                  placeholder="••••••••"
                  value={formData.password_confirmation}
                  onChange={handleChange}
                  disabled={loading}
                />
              </div>

              <div className="flex items-start gap-2 p-3 text-sm text-muted-foreground bg-muted/50 border border-border rounded-md">
                <Info className="h-4 w-4 mt-0.5 flex-shrink-0" />
                <p>
                  След регистрация, вашият акаунт ще чака одобрение от администратор.
                </p>
              </div>

              {error && (
                <div className="flex items-center gap-2 p-3 text-sm text-destructive bg-destructive/10 border border-destructive/20 rounded-md">
                  <AlertCircle className="h-4 w-4" />
                  <span>{error}</span>
                </div>
              )}

              <Button
                type="submit"
                className="w-full"
                disabled={loading}
              >
                {loading ? 'Регистриране...' : 'Регистрирай се'}
              </Button>

              <div className="text-center text-sm">
                <span className="text-muted-foreground">Вече имате акаунт? </span>
                <Link
                  href="/login"
                  className="font-medium text-primary hover:underline"
                >
                  Влезте
                </Link>
              </div>
            </form>
          </CardContent>
        </Card>

        <div className="text-center">
          <Link
            href="/"
            className="text-sm text-muted-foreground hover:text-foreground transition-colors"
          >
            ← Назад към началната страница
          </Link>
        </div>
      </div>
    </div>
  );
}
