<?php
namespace Tests\Feature\CmsPages;
use App\Enums\{CmsPageStatus,CmsPageType}; use App\Models\{CmsPage,User}; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class CmsPageTest extends TestCase { use RefreshDatabase;
 public function test_public_published_page_renders_and_draft_is_hidden():void{$p=CmsPage::create(['title'=>'About','slug'=>'about-us','page_type'=>CmsPageType::Standard,'status'=>CmsPageStatus::Published,'excerpt'=>'About copy']);$p->sections()->create(['type'=>'text','title'=>'About','content'=>'Trusted marketplace']);$this->get('/pages/about-us')->assertOk()->assertSeeText('Trusted marketplace');$p->update(['status'=>CmsPageStatus::Draft]);$this->get('/pages/about-us')->assertNotFound();}
 public function test_admin_page_routes_are_role_protected_and_reserved_slugs_rejected():void{$customer=User::factory()->create();$this->actingAs($customer)->get('/admin/pages')->assertForbidden();$this->actingAs(User::factory()->admin()->create())->post('/admin/pages',['title'=>'Bad','slug'=>'cart','page_type'=>'standard','status'=>'draft'])->assertStatus(422);}
}

