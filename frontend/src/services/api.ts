const API_BASE_URL = 'http://localhost:8201/api';

interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
  pagination?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// Helper function for API calls
async function apiCall<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<ApiResponse<T>> {
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...options.headers,
    },
    credentials: 'include',
  });

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.message || 'An error occurred');
  }

  return data;
}

// AI Tools API
export const toolsApi = {
  // Get all tools
  getAll: async (params?: {
    status?: string;
    category_id?: number;
    role?: string;
    featured?: boolean;
    search?: string;
    sort_by?: string;
    sort_order?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
  }) => {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          queryParams.append(key, String(value));
        }
      });
    }
    const query = queryParams.toString();
    return apiCall<any[]>(`/tools${query ? `?${query}` : ''}`);
  },

  // Get single tool
  getOne: async (slug: string) => {
    return apiCall<any>(`/tools/${slug}`);
  },

  // Create tool
  create: async (toolData: any) => {
    return apiCall<any>('/tools', {
      method: 'POST',
      body: JSON.stringify(toolData),
    });
  },

  // Update tool
  update: async (slug: string, toolData: any) => {
    return apiCall<any>(`/tools/${slug}`, {
      method: 'PUT',
      body: JSON.stringify(toolData),
    });
  },

  // Delete tool
  delete: async (slug: string) => {
    return apiCall<void>(`/tools/${slug}`, {
      method: 'DELETE',
    });
  },

  // Toggle like
  toggleLike: async (slug: string) => {
    return apiCall<{ liked: boolean; likes_count: number }>(`/tools/${slug}/like`, {
      method: 'POST',
    });
  },
};

// Categories API
export const categoriesApi = {
  // Get all categories
  getAll: async (params?: {
    active?: boolean;
    parent_id?: number | 'null';
    with_counts?: boolean;
  }) => {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          queryParams.append(key, String(value));
        }
      });
    }
    const query = queryParams.toString();
    return apiCall<any[]>(`/categories${query ? `?${query}` : ''}`);
  },

  // Get single category
  getOne: async (slug: string) => {
    return apiCall<any>(`/categories/${slug}`);
  },

  // Create category (owner only)
  create: async (categoryData: any) => {
    return apiCall<any>('/categories', {
      method: 'POST',
      body: JSON.stringify(categoryData),
    });
  },

  // Update category (owner only)
  update: async (slug: string, categoryData: any) => {
    return apiCall<any>(`/categories/${slug}`, {
      method: 'PUT',
      body: JSON.stringify(categoryData),
    });
  },

  // Delete category (owner only)
  delete: async (slug: string) => {
    return apiCall<void>(`/categories/${slug}`, {
      method: 'DELETE',
    });
  },
};

