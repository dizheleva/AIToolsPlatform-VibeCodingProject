'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, Save, X } from 'lucide-react';
import { toolsApi, categoriesApi } from '@/services/api';
import MainLayout from '@/components/layout/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { useToast } from '@/hooks/use-toast';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  status: string;
}

interface Category {
  id: number;
  name: string;
  slug: string;
  icon?: string;
}

export default function NewToolPage() {
  const [user, setUser] = useState<User | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(false);
  const router = useRouter();
  const { toast } = useToast();

  const [formData, setFormData] = useState({
    name: '',
    description: '',
    short_description: '',
    url: '',
    logo_url: '',
    pricing_model: 'free',
    category_ids: [] as number[],
    roles: [] as string[],
    tags: [] as string[],
    documentation_url: '',
    github_url: '',
  });

  const pricingModels = [
    { value: 'free', label: 'Безплатно' },
    { value: 'freemium', label: 'Freemium' },
    { value: 'paid', label: 'Платено' },
    { value: 'enterprise', label: 'Enterprise' },
  ];

  const availableRoles = [
    { value: 'backend', label: 'Backend Developer' },
    { value: 'frontend', label: 'Frontend Developer' },
    { value: 'qa', label: 'QA Engineer' },
    { value: 'pm', label: 'Project Manager' },
    { value: 'designer', label: 'Designer' },
  ];

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      const userData = JSON.parse(storedUser);
      setUser(userData);
      
      if (userData.status !== 'approved') {
        router.push('/tools');
        return;
      }
    } else {
      router.push('/login');
      return;
    }

    loadCategories();
  }, []);

  const loadCategories = async () => {
    try {
      const response = await categoriesApi.getAll({ active: true });
      if (response.success && response.data) {
        setCategories(Array.isArray(response.data) ? response.data : []);
      }
    } catch (error) {
      console.error('Error loading categories:', error);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleCategoryToggle = (categoryId: number) => {
    setFormData(prev => ({
      ...prev,
      category_ids: prev.category_ids.includes(categoryId)
        ? prev.category_ids.filter(id => id !== categoryId)
        : [...prev.category_ids, categoryId]
    }));
  };

  const handleRoleToggle = (role: string) => {
    setFormData(prev => ({
      ...prev,
      roles: prev.roles.includes(role)
        ? prev.roles.filter(r => r !== role)
        : [...prev.roles, role]
    }));
  };

  const handleTagsChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const tags = e.target.value.split(',').map(tag => tag.trim()).filter(tag => tag);
    setFormData(prev => ({ ...prev, tags }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await toolsApi.create(formData);
      if (response.success) {
        toast({
          title: 'Успешно!',
          description: 'Инструментът беше създаден успешно.',
          variant: 'success',
        });
        setTimeout(() => {
          router.push(`/tools/${response.data?.slug || ''}`);
        }, 1000);
      } else {
        toast({
          title: 'Грешка',
          description: response.message || 'Грешка при създаване на инструмент',
          variant: 'destructive',
        });
      }
    } catch (err: any) {
      toast({
        title: 'Грешка',
        description: err.message || 'Грешка при създаване на инструмент',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  if (!user) {
    return null;
  }

  return (
    <MainLayout containerSize="lg">
      {/* Header */}
      <div className="mb-6">
        <Link href="/tools">
          <Button variant="ghost" className="mb-4">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Назад към инструменти
          </Button>
        </Link>
        <h1 className="text-3xl font-bold text-gray-900 mb-2">Добави нов AI инструмент</h1>
        <p className="text-gray-600">Сподели нов AI инструмент с екипа си</p>
      </div>

      <form onSubmit={handleSubmit}>
        <div className="space-y-6">
          {/* Basic Information */}
          <Card>
            <CardHeader>
              <CardTitle>Основна информация</CardTitle>
              <CardDescription>Въведи основната информация за инструмента</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Име на инструмента *</Label>
                <Input
                  id="name"
                  name="name"
                  required
                  value={formData.name}
                  onChange={handleChange}
                  placeholder="Например: ChatGPT"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="short_description">Кратко описание</Label>
                <Input
                  id="short_description"
                  name="short_description"
                  maxLength={500}
                  value={formData.short_description}
                  onChange={handleChange}
                  placeholder="Кратко описание (до 500 символа)"
                />
                <p className="text-xs text-muted-foreground">
                  {formData.short_description.length}/500
                </p>
              </div>

              <div className="space-y-2">
                <Label htmlFor="description">Пълно описание</Label>
                <Textarea
                  id="description"
                  name="description"
                  rows={6}
                  value={formData.description}
                  onChange={handleChange}
                  placeholder="Подробно описание на инструмента..."
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="url">URL на инструмента *</Label>
                <Input
                  type="url"
                  id="url"
                  name="url"
                  required
                  value={formData.url}
                  onChange={handleChange}
                  placeholder="https://example.com"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="logo_url">URL на лого</Label>
                <Input
                  type="url"
                  id="logo_url"
                  name="logo_url"
                  value={formData.logo_url}
                  onChange={handleChange}
                  placeholder="https://example.com/logo.png"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="pricing_model">Модел на ценообразуване *</Label>
                <Select
                  id="pricing_model"
                  name="pricing_model"
                  required
                  value={formData.pricing_model}
                  onChange={handleChange}
                >
                  {pricingModels.map(model => (
                    <option key={model.value} value={model.value}>
                      {model.label}
                    </option>
                  ))}
                </Select>
              </div>
            </CardContent>
          </Card>

          {/* Categories */}
          <Card>
            <CardHeader>
              <CardTitle>Категории</CardTitle>
              <CardDescription>Избери категориите, към които принадлежи инструментът</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                {categories.map(category => (
                  <label
                    key={category.id}
                    className="flex items-center space-x-2 p-3 border rounded-md cursor-pointer hover:bg-accent transition-colors"
                  >
                    <Checkbox
                      checked={formData.category_ids.includes(category.id)}
                      onChange={() => handleCategoryToggle(category.id)}
                    />
                    <span className="text-sm">
                      {category.icon && <span className="mr-1">{category.icon}</span>}
                      {category.name}
                    </span>
                  </label>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Roles */}
          <Card>
            <CardHeader>
              <CardTitle>Подходящи роли</CardTitle>
              <CardDescription>Избери ролите, за които е подходящ този инструмент</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                {availableRoles.map(role => (
                  <label
                    key={role.value}
                    className="flex items-center space-x-2 p-3 border rounded-md cursor-pointer hover:bg-accent transition-colors"
                  >
                    <Checkbox
                      checked={formData.roles.includes(role.value)}
                      onChange={() => handleRoleToggle(role.value)}
                    />
                    <span className="text-sm">{role.label}</span>
                  </label>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Additional Information */}
          <Card>
            <CardHeader>
              <CardTitle>Допълнителна информация</CardTitle>
              <CardDescription>Опционална допълнителна информация</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="tags">Тагове (разделени със запетая)</Label>
                <Input
                  type="text"
                  id="tags"
                  value={formData.tags.join(', ')}
                  onChange={handleTagsChange}
                  placeholder="code, ai, automation"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="documentation_url">URL на документация</Label>
                <Input
                  type="url"
                  id="documentation_url"
                  name="documentation_url"
                  value={formData.documentation_url}
                  onChange={handleChange}
                  placeholder="https://docs.example.com"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="github_url">GitHub URL</Label>
                <Input
                  type="url"
                  id="github_url"
                  name="github_url"
                  value={formData.github_url}
                  onChange={handleChange}
                  placeholder="https://github.com/example"
                />
              </div>
            </CardContent>
          </Card>

          {/* Submit Buttons */}
          <div className="flex justify-end space-x-4">
            <Link href="/tools">
              <Button type="button" variant="outline">
                <X className="mr-2 h-4 w-4" />
                Отказ
              </Button>
            </Link>
            <Button type="submit" disabled={loading}>
              <Save className="mr-2 h-4 w-4" />
              {loading ? 'Запазване...' : 'Запази'}
            </Button>
          </div>
        </div>
      </form>
    </MainLayout>
  );
}
