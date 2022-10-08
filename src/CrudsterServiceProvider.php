<?php
    namespace Jbuapim\Crudster;

    use Illuminate\Support\ServiceProvider;

    class CrudsterServiceProvider extends ServiceProvider{
        public function boot(){
         
        }

        public function register()
        {
         $this->app->singleton(Crudster::class,function(){
            return new Crudster();
         });
   
        }
    }
?>