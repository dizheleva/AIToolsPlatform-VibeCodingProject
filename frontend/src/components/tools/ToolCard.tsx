'use client'

import Link from 'next/link'
import { Eye, Heart, ExternalLink, Star } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

interface Category {
  id: number
  name: string
  slug: string
  icon?: string
  color?: string
}

interface ToolCardProps {
  id: number
  name: string
  slug: string
  short_description?: string
  url: string
  logo_url?: string
  pricing_model: string
  featured: boolean
  views_count: number
  likes_count: number
  categories?: Category[]
  className?: string
}

export default function ToolCard({
  name,
  slug,
  short_description,
  url,
  logo_url,
  pricing_model,
  featured,
  views_count,
  likes_count,
  categories,
  className,
}: ToolCardProps) {
  const pricingColors: Record<string, string> = {
    free: 'bg-green-100 text-green-800',
    paid: 'bg-blue-100 text-blue-800',
    freemium: 'bg-purple-100 text-purple-800',
    'free trial': 'bg-amber-100 text-amber-800',
  }

  return (
    <Link href={`/tools/${slug}`}>
      <Card
        className={cn(
          'hover:shadow-lg transition-all duration-200 cursor-pointer h-full group',
          className
        )}
      >
        <CardHeader>
          <div className="flex items-start justify-between gap-3 mb-2">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 mb-1">
                {logo_url && (
                  <img
                    src={logo_url}
                    alt={name}
                    className="w-8 h-8 rounded object-cover flex-shrink-0"
                  />
                )}
                <CardTitle className="text-lg font-semibold line-clamp-1 group-hover:text-primary transition-colors">
                  {name}
                </CardTitle>
              </div>
              {featured && (
                <Badge variant="warning" className="mt-1">
                  <Star className="h-3 w-3 mr-1 fill-current" />
                  Featured
                </Badge>
              )}
            </div>
            <ExternalLink className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0" />
          </div>
          {short_description && (
            <CardDescription className="line-clamp-2 min-h-[2.5rem]">
              {short_description}
            </CardDescription>
          )}
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {/* Categories */}
            {categories && categories.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {categories.slice(0, 3).map((cat) => (
                  <Badge
                    key={cat.id}
                    variant="outline"
                    className="text-xs"
                  >
                    {cat.icon && <span className="mr-1">{cat.icon}</span>}
                    {cat.name}
                  </Badge>
                ))}
                {categories.length > 3 && (
                  <Badge variant="outline" className="text-xs">
                    +{categories.length - 3}
                  </Badge>
                )}
              </div>
            )}

            {/* Footer */}
            <div className="flex items-center justify-between pt-2 border-t">
              <Badge
                variant="outline"
                className={cn(
                  'capitalize',
                  pricingColors[pricing_model.toLowerCase()] || 'bg-gray-100 text-gray-800'
                )}
              >
                {pricing_model}
              </Badge>
              <div className="flex items-center gap-4 text-sm text-muted-foreground">
                <div className="flex items-center gap-1">
                  <Eye className="h-4 w-4" />
                  <span>{views_count}</span>
                </div>
                <div className="flex items-center gap-1">
                  <Heart className="h-4 w-4" />
                  <span>{likes_count}</span>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </Link>
  )
}

