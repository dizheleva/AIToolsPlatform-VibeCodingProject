'use client'

import { Search, Filter } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

interface Category {
  id: number
  name: string
  slug: string
  icon?: string
}

interface ToolFiltersProps {
  searchQuery: string
  onSearchChange: (value: string) => void
  onSearch: () => void
  selectedCategory: number | null
  onCategoryChange: (categoryId: number | null) => void
  categories: Category[]
}

export default function ToolFilters({
  searchQuery,
  onSearchChange,
  onSearch,
  selectedCategory,
  onCategoryChange,
  categories,
}: ToolFiltersProps) {
  const selectedCategoryName = categories.find((cat) => cat.id === selectedCategory)?.name || 'Всички категории'

  return (
    <div className="flex flex-col sm:flex-row gap-3">
      <div className="flex-1 relative">
        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <Input
          type="text"
          placeholder="Търсене по име или описание..."
          value={searchQuery}
          onChange={(e) => onSearchChange(e.target.value)}
          onKeyPress={(e) => e.key === 'Enter' && onSearch()}
          className="pl-10"
        />
      </div>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" className="w-full sm:w-auto">
            <Filter className="h-4 w-4 mr-2" />
            {selectedCategoryName}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-56">
          <DropdownMenuLabel>Категории</DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => onCategoryChange(null)}>
            Всички категории
          </DropdownMenuItem>
          {categories.map((cat) => (
            <DropdownMenuItem
              key={cat.id}
              onClick={() => onCategoryChange(cat.id)}
            >
              {cat.icon && <span className="mr-2">{cat.icon}</span>}
              {cat.name}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>

      <Button onClick={onSearch} className="w-full sm:w-auto">
        Търси
      </Button>
    </div>
  )
}

