'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Plus, Wrench, ChevronLeft, ChevronRight } from 'lucide-react';
import { toolsApi, categoriesApi } from '@/services/api';
import MainLayout from '@/components/layout/MainLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import ToolCard from '@/components/tools/ToolCard';
import ToolFilters from '@/components/tools/ToolFilters';

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
  short_description?: string;
  description?: string;
  url: string;
  logo_url?: string;
  pricing_model: string;
  status: string;
  featured: boolean;
  views_count: number;
  likes_count: number;
  categories?: Category[];
  roles?: string[];
  creator?: {
    id: number;
    name: string;
  };
}

export default function ToolsPage() {
  const [user, setUser] = useState<User | null>(null);
  const [tools, setTools] = useState<AiTool[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const router = useRouter();

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      setUser(JSON.parse(storedUser));
    } else {
      router.push('/login');
      return;
    }

    loadCategories();
    loadData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (user) {
      loadData();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentPage, selectedCategory, searchQuery]);

  const loadCategories = async () => {
    try {
      const categoriesResponse = await categoriesApi.getAll({ active: true });
      if (categoriesResponse.success && categoriesResponse.data) {
        setCategories(Array.isArray(categoriesResponse.data) ? categoriesResponse.data : []);
      }
    } catch (error) {
      console.error('Error loading categories:', error);
    }
  };

  const loadData = async () => {
    try {
      setLoading(true);
      const params: {
        status: string;
        category_id?: number;
        search?: string;
        page?: number;
        per_page?: number;
      } = { 
        status: 'active',
        page: currentPage,
        per_page: 12,
      };
      
      if (selectedCategory) params.category_id = selectedCategory;
      if (searchQuery) params.search = searchQuery;

      const toolsResponse = await toolsApi.getAll(params);

      if (toolsResponse.success && toolsResponse.data) {
        setTools(Array.isArray(toolsResponse.data) ? toolsResponse.data : []);
        
        if (toolsResponse.pagination) {
          setTotalPages(toolsResponse.pagination.last_page);
          setTotal(toolsResponse.pagination.total);
        }
      }
    } catch (error) {
      console.error('Error loading data:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = () => {
    setCurrentPage(1);
    loadData();
  };


  const canCreate = user && user.status === 'approved';

  if (loading && !tools.length) {
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
    <MainLayout>
      {/* Header */}
      <div className="mb-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 mb-2">AI Tools</h1>
            <p className="text-gray-600">
              Открий и използвай най-добрите AI инструменти за твоята работа
            </p>
          </div>
          {canCreate && (
            <Link href="/tools/new">
              <Button>
                <Plus className="mr-2 h-4 w-4" />
                Добави инструмент
              </Button>
            </Link>
          )}
        </div>

        {/* Filters */}
        <ToolFilters
          searchQuery={searchQuery}
          onSearchChange={setSearchQuery}
          onSearch={handleSearch}
          selectedCategory={selectedCategory}
          onCategoryChange={(categoryId) => {
            setSelectedCategory(categoryId);
            setCurrentPage(1);
          }}
          categories={categories}
        />
      </div>

      {/* Tools Grid */}
      {tools.length === 0 ? (
        <Card>
          <CardContent className="py-12">
            <div className="text-center">
              <Wrench className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <h3 className="text-lg font-semibold text-gray-900 mb-2">
                Няма намерени инструменти
              </h3>
              <p className="text-gray-600 mb-4">
                {searchQuery || selectedCategory
                  ? 'Опитай с различни критерии за търсене'
                  : 'Все още няма добавени инструменти'}
              </p>
              {canCreate && !searchQuery && !selectedCategory && (
                <Link href="/tools/new">
                  <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Добави първия инструмент
                  </Button>
                </Link>
              )}
            </div>
          </CardContent>
        </Card>
      ) : (
        <>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            {tools.map((tool) => (
              <ToolCard
                key={tool.id}
                id={tool.id}
                name={tool.name}
                slug={tool.slug}
                short_description={tool.short_description}
                url={tool.url}
                logo_url={tool.logo_url}
                pricing_model={tool.pricing_model}
                featured={tool.featured}
                views_count={tool.views_count}
                likes_count={tool.likes_count}
                categories={tool.categories}
              />
            ))}
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center justify-between">
                  <p className="text-sm text-muted-foreground">
                    Показване на {((currentPage - 1) * 12) + 1}-{Math.min(currentPage * 12, total)} от {total} инструмента
                  </p>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                      disabled={currentPage === 1 || loading}
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
                            disabled={loading}
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
                      disabled={currentPage === totalPages || loading}
                    >
                      Следваща
                      <ChevronRight className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}
        </>
      )}
    </MainLayout>
  );
}

