'use client';

import { useState, useEffect } from 'react';
import { useRouter, useParams } from 'next/navigation';
import Link from 'next/link';
import Image from 'next/image';
import { ArrowLeft, ExternalLink, Heart, Eye, Edit, Trash2, Star, Calendar, User, Globe, Book, Github } from 'lucide-react';
import { toolsApi } from '@/services/api';
import MainLayout from '@/components/layout/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';
import { getRoleDisplayName } from '@/lib/utils';

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
  color?: string;
}

interface AiTool {
  id: number;
  name: string;
  slug: string;
  description?: string;
  short_description?: string;
  url: string;
  logo_url?: string;
  pricing_model: string;
  status: string;
  featured: boolean;
  views_count: number;
  likes_count: number;
  documentation_url?: string;
  github_url?: string;
  tags?: string[];
  categories?: Category[];
  roles?: string[];
  creator?: {
    id: number;
    name: string;
  };
  created_at: string;
  is_liked?: boolean;
}

export default function ToolDetailPage() {
  const [user, setUser] = useState<User | null>(null);
  const [tool, setTool] = useState<AiTool | null>(null);
  const [loading, setLoading] = useState(true);
  const [liked, setLiked] = useState(false);
  const router = useRouter();
  const params = useParams();
  const slug = params.slug as string;
  const { toast } = useToast();

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      setUser(JSON.parse(storedUser));
    }

    loadTool();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug]);

  const loadTool = async () => {
    try {
      setLoading(true);
      const response = await toolsApi.getOne(slug);
      if (response.success && response.data) {
        setTool(response.data);
        // Set liked state from the response
        setLiked(response.data.is_liked || false);
      } else {
        toast({
          title: 'Грешка',
          description: 'Инструментът не беше намерен.',
          variant: 'destructive',
        });
      }
    } catch (error) {
      console.error('Error loading tool:', error);
      toast({
        title: 'Грешка',
        description: 'Възникна грешка при зареждане на инструмента.',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const handleLike = async () => {
    if (!user) {
      router.push('/login');
      return;
    }

    try {
      const response = await toolsApi.toggleLike(slug);
      console.log('Like response:', response);
      
      if (response.success && response.data) {
        const newLikedState = Boolean(response.data.liked);
        const newLikesCount = response.data.likes_count ?? tool?.likes_count ?? 0;
        
        console.log('New liked state:', newLikedState, 'Current state:', liked);
        console.log('New likes count:', newLikesCount);
        
        // Update state
        setLiked(newLikedState);
        
        if (tool) {
          setTool({ 
            ...tool, 
            likes_count: newLikesCount,
            is_liked: newLikedState
          });
        }
        
        toast({
          title: newLikedState ? 'Харесано!' : 'Премахнато от харесани',
          description: newLikedState 
            ? 'Инструментът беше добавен към твоите харесани.'
            : 'Инструментът беше премахнат от твоите харесани.',
          variant: newLikedState ? 'success' : 'default',
        });
      } else {
        console.error('Response not successful or missing data:', response);
      }
    } catch (error) {
      console.error('Error toggling like:', error);
      toast({
        title: 'Грешка',
        description: 'Възникна грешка при харесване на инструмента.',
        variant: 'destructive',
      });
    }
  };

  const handleDelete = async () => {
    if (!confirm('Сигурни ли сте, че искате да изтриете този инструмент?')) {
      return;
    }

    try {
      const response = await toolsApi.delete(slug);
      if (response.success) {
        toast({
          title: 'Успешно изтрит',
          description: 'Инструментът беше изтрит успешно.',
          variant: 'success',
        });
        router.push('/tools');
      }
    } catch (error) {
      console.error('Error deleting tool:', error);
      toast({
        title: 'Грешка',
        description: 'Възникна грешка при изтриване на инструмента.',
        variant: 'destructive',
      });
    }
  };

  const canEdit = user && tool && (
    (user.role === 'owner' && user.status === 'approved') ||
    (tool.creator?.id === user.id && user.status === 'approved')
  );

  const getPricingLabel = (model: string) => {
    const labels: { [key: string]: string } = {
      free: 'Безплатно',
      freemium: 'Freemium',
      paid: 'Платено',
      enterprise: 'Enterprise',
    };
    return labels[model] || model;
  };

  const pricingColors: Record<string, string> = {
    free: 'bg-green-100 text-green-800',
    paid: 'bg-blue-100 text-blue-800',
    freemium: 'bg-purple-100 text-purple-800',
    'free trial': 'bg-amber-100 text-amber-800',
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

  if (!tool) {
    return (
      <MainLayout>
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <p className="text-gray-600 text-lg mb-4">Инструментът не е намерен.</p>
            <Link href="/tools">
              <Button variant="outline">
                <ArrowLeft className="mr-2 h-4 w-4" />
                Назад към инструменти
              </Button>
            </Link>
          </div>
        </div>
      </MainLayout>
    );
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
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Tool Header */}
          <Card>
            <CardHeader>
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    {tool.logo_url && (
                      <div className="relative w-12 h-12 rounded overflow-hidden">
                        <Image
                          src={tool.logo_url}
                          alt={tool.name}
                          fill
                          className="object-cover"
                          unoptimized
                        />
                      </div>
                    )}
                    <CardTitle className="text-3xl">{tool.name}</CardTitle>
                    {tool.featured && (
                      <Badge variant="warning">
                        <Star className="h-3 w-3 mr-1 fill-current" />
                        Featured
                      </Badge>
                    )}
                  </div>
                  {tool.short_description && (
                    <CardDescription className="text-base mt-2">
                      {tool.short_description}
                    </CardDescription>
                  )}
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                <div className="flex items-center gap-1">
                  <Eye className="h-4 w-4" />
                  <span>{tool.views_count} преглеждания</span>
                </div>
                <div className="flex items-center gap-1">
                  <Heart className={`h-4 w-4 ${liked ? 'fill-red-500 text-red-500' : 'text-muted-foreground'}`} />
                  <span>{tool.likes_count} харесвания</span>
                </div>
                <Badge
                  variant="outline"
                  className={pricingColors[tool.pricing_model.toLowerCase()] || 'bg-gray-100 text-gray-800'}
                >
                  {getPricingLabel(tool.pricing_model)}
                </Badge>
              </div>
            </CardContent>
          </Card>

          {/* Description */}
          {tool.description && (
            <Card>
              <CardHeader>
                <CardTitle>Описание</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-gray-700 whitespace-pre-wrap leading-relaxed">
                  {tool.description}
                </p>
              </CardContent>
            </Card>
          )}

          {/* Categories & Tags */}
          {(tool.categories && tool.categories.length > 0) || (tool.tags && tool.tags.length > 0) ? (
            <Card>
              <CardHeader>
                <CardTitle>Категории и тагове</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                {tool.categories && tool.categories.length > 0 && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground mb-2">Категории</p>
                    <div className="flex flex-wrap gap-2">
                      {tool.categories.map(cat => (
                        <Badge key={cat.id} variant="outline" className="text-sm">
                          {cat.icon && <span className="mr-1">{cat.icon}</span>}
                          {cat.name}
                        </Badge>
                      ))}
                    </div>
                  </div>
                )}
                {tool.tags && tool.tags.length > 0 && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground mb-2">Тагове</p>
                    <div className="flex flex-wrap gap-2">
                      {tool.tags.map((tag, index) => (
                        <Badge key={index} variant="outline" className="text-sm">
                          #{tag}
                        </Badge>
                      ))}
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          ) : null}

          {/* Roles */}
          {tool.roles && tool.roles.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>Подходящи роли</CardTitle>
                <CardDescription>Този инструмент е подходящ за следните роли</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex flex-wrap gap-2">
                  {tool.roles.map(role => (
                    <Badge key={role} variant="secondary" className="text-sm capitalize">
                      {getRoleDisplayName(role)}
                    </Badge>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}

          {/* Links */}
          <Card>
            <CardHeader>
              <CardTitle>Линкове</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <a
                href={tool.url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-3 p-3 border rounded-md hover:bg-accent transition-colors"
              >
                <Globe className="h-5 w-5 text-primary" />
                <div className="flex-1">
                  <p className="font-medium">Официален сайт</p>
                  <p className="text-sm text-muted-foreground">{tool.url}</p>
                </div>
                <ExternalLink className="h-4 w-4 text-muted-foreground" />
              </a>
              {tool.documentation_url && (
                <a
                  href={tool.documentation_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-3 p-3 border rounded-md hover:bg-accent transition-colors"
                >
                  <Book className="h-5 w-5 text-primary" />
                  <div className="flex-1">
                    <p className="font-medium">Документация</p>
                    <p className="text-sm text-muted-foreground">{tool.documentation_url}</p>
                  </div>
                  <ExternalLink className="h-4 w-4 text-muted-foreground" />
                </a>
              )}
              {tool.github_url && (
                <a
                  href={tool.github_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-3 p-3 border rounded-md hover:bg-accent transition-colors"
                >
                  <Github className="h-5 w-5 text-primary" />
                  <div className="flex-1">
                    <p className="font-medium">GitHub</p>
                    <p className="text-sm text-muted-foreground">{tool.github_url}</p>
                  </div>
                  <ExternalLink className="h-4 w-4 text-muted-foreground" />
                </a>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Actions */}
          <Card>
            <CardHeader>
              <CardTitle>Действия</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
              {user && (
                <Button
                  onClick={handleLike}
                  variant="outline"
                  className={`w-full ${liked ? 'bg-red-50 hover:bg-red-100 border-red-200' : ''}`}
                >
                  <Heart className={`mr-2 h-4 w-4 ${liked ? 'fill-red-500 text-red-500' : ''}`} />
                  {liked ? 'Харесано' : 'Харесай'}
                </Button>
              )}
              <a
                href={tool.url}
                target="_blank"
                rel="noopener noreferrer"
                className="w-full block"
              >
                <Button className="w-full">
                  <ExternalLink className="mr-2 h-4 w-4" />
                  Отвори инструмент
                </Button>
              </a>
              {canEdit && (
                <>
                  <Link href={`/tools/${tool.slug}/edit`} className="w-full block">
                    <Button variant="outline" className="w-full bg-amber-200 hover:bg-amber-300 border-amber-400 text-amber-900 hover:text-amber-950">
                      <Edit className="mr-2 h-4 w-4" />
                      Редактирай
                    </Button>
                  </Link>
                  <Button
                    onClick={handleDelete}
                    variant="destructive"
                    className="w-full"
                  >
                    <Trash2 className="mr-2 h-4 w-4" />
                    Изтрий
                  </Button>
                </>
              )}
            </CardContent>
          </Card>

          {/* Meta Information */}
          <Card>
            <CardHeader>
              <CardTitle>Информация</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {tool.creator && (
                <div className="flex items-center gap-3">
                  <User className="h-4 w-4 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Създаден от</p>
                    <p className="text-sm font-semibold">{tool.creator.name}</p>
                  </div>
                </div>
              )}
              <div className="flex items-center gap-3">
                <Calendar className="h-4 w-4 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Създаден на</p>
                  <p className="text-sm font-semibold">
                    {new Date(tool.created_at).toLocaleDateString('bg-BG', {
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                    })}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </MainLayout>
  );
}
