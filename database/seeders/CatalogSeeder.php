<?php

/**
 * Startovní sada katalogu (R29, R33) — běžný nákup: mléčné výrobky, maso, pečivo, trvanlivé
 * potraviny, ovoce a zelenina, nápoje a drogerie. Pravidla jsou odladěná na ostrých akcích
 * všech obchodů 2. 10. 2026 (6 245 nabídek); vyloučená slova odstraňují skutečné chybné
 * shody (příchutě, dětské příkrmy, krmiva, hotová jídla). Kategorie se přiřadí, jen když je
 * strom stažený (`letaky:import-categories`).
 *
 * Vyloučená slova jsou ze skutečných nabídek 2. 10. 2026: „MAGGI Přidej vejce“, „toustový chléb
 * s vejcem“, „Ruské vejce“, ochucený „Lipánek“ (tuk 1,3–1,5 %), „máslová dýně“, „máslový
 * karamel“. Slova se hledají jako začátek slova, takže „máslov“ vyřadí máslová, máslový i máslové.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Actions\AssignProducts;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /** Produkty s pravidly a cestou ke kategorii ve stromu Tesca. */
    private const PRODUCTS = [
        ['name' => 'Vejce', 'keywords' => 'vejce', 'variant_keywords' => null, 'exclude_keywords' => 'maggi polévka těstoviny toust aspik pomazánka bageta ruské',
            'category' => ['Mléčné, vejce a margaríny', 'Vejce a droždí', 'Vejce']],
        ['name' => 'Polotučné mléko', 'keywords' => 'mléko polotučné|1,5', 'variant_keywords' => null, 'exclude_keywords' => 'kefír kokos zakysané acidofil čokoláda lipánek ochucené',
            'category' => ['Mléčné, vejce a margaríny', 'Mléko, mléčné a jogurtové nápoje']],
        ['name' => 'Plnotučné mléko', 'keywords' => 'mléko plnotučné|3,5', 'variant_keywords' => null, 'exclude_keywords' => 'kefír kokos zakysané acidofil čokoláda lipánek ochucené jogurt',
            'category' => ['Mléčné, vejce a margaríny', 'Mléko, mléčné a jogurtové nápoje']],
        ['name' => 'Máslo', 'keywords' => 'máslo', 'variant_keywords' => null, 'exclude_keywords' => 'máslov arašíd kakao bylink pomazánk sušenk',
            'category' => ['Mléčné, vejce a margaríny', 'Máslo, margaríny a pomazánky', 'Máslo']],
        ['name' => 'Smetana ke šlehání', 'keywords' => 'smetana šlehání|33|31|30', 'variant_keywords' => null, 'exclude_keywords' => 'zakysan kysan vaření',
            'category' => ['Mléčné, vejce a margaríny', 'Smetany, šlehačky a zakysané výrobky']],
        ['name' => 'Zakysaná smetana', 'keywords' => 'zakysaná smetana', 'variant_keywords' => null, 'exclude_keywords' => 'příchu chips chipsy popchips snack brambůrk',
            'category' => ['Mléčné, vejce a margaríny', 'Smetany, šlehačky a zakysané výrobky']],
        ['name' => 'Tvaroh', 'keywords' => 'tvaroh', 'variant_keywords' => null, 'exclude_keywords' => 'tvarohov mřížk dezert koláč šáteč jogurt termix kiri žervé hamánek tavený',
            'category' => ['Mléčné, vejce a margaríny', 'Sýry a tvarohy', 'Tvaroh a Mascarpone']],
        ['name' => 'Bílý jogurt', 'keywords' => 'jogurt bílý', 'variant_keywords' => null, 'exclude_keywords' => 'nápoj',
            'category' => ['Mléčné, vejce a margaríny', 'Jogurty a dezerty', 'Bílé jogurty']],
        ['name' => 'Eidam', 'keywords' => 'eidam|eidamsk', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Mléčné, vejce a margaríny', 'Sýry a tvarohy', 'Bločky a strouhaný sýr', 'Eidam']],
        ['name' => 'Gouda', 'keywords' => 'gouda', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Mléčné, vejce a margaríny', 'Sýry a tvarohy', 'Plátkový sýr', 'Gouda']],
        ['name' => 'Mozzarella', 'keywords' => 'mozzarella|mozarella', 'variant_keywords' => null, 'exclude_keywords' => 'pizza focaccia',
            'category' => ['Mléčné, vejce a margaríny', 'Sýry a tvarohy', 'Mozarella, Ricotta a Salátové sýry', 'Mozarella']],
        ['name' => 'Hermelín', 'keywords' => 'hermelín', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Mléčné, vejce a margaríny', 'Sýry a tvarohy', 'Plísňové a zrajíci sýry', 'Sýry s bílou plísní']],
        ['name' => 'Margarín', 'keywords' => 'rama|flora|margarín|rostlinná', 'variant_keywords' => null, 'exclude_keywords' => 'floral chryzantém perfumes toaletní utěrk pomazánk tofu',
            'category' => ['Mléčné, vejce a margaríny', 'Máslo, margaríny a pomazánky', 'Margaríny']],
        ['name' => 'Kuřecí prsa', 'keywords' => 'kuřecí prs', 'variant_keywords' => null, 'exclude_keywords' => 'těstoviny omáčk sendvič bageta salát šunka uzen',
            'category' => ['Maso a lahůdky', 'Maso, ryby a speciality', 'Drůbeží']],
        ['name' => 'Kuřecí stehna', 'keywords' => 'kuřecí stehn|čtvrtky', 'variant_keywords' => null, 'exclude_keywords' => 'řízky',
            'category' => ['Maso a lahůdky', 'Maso, ryby a speciality', 'Drůbeží']],
        ['name' => 'Mleté maso', 'keywords' => 'mleté maso', 'variant_keywords' => null, 'exclude_keywords' => 'kotányi koření',
            'category' => ['Maso a lahůdky', 'Maso, ryby a speciality', 'Mleté']],
        ['name' => 'Vepřová krkovice', 'keywords' => 'krkovice|krkovička', 'variant_keywords' => null, 'exclude_keywords' => 'kotányi koření',
            'category' => ['Maso a lahůdky', 'Maso, ryby a speciality', 'Vepřové']],
        ['name' => 'Vepřová pečeně', 'keywords' => 'vepřov pečen|kotlet', 'variant_keywords' => null, 'exclude_keywords' => 'kotányi koření',
            'category' => ['Maso a lahůdky', 'Maso, ryby a speciality', 'Vepřové']],
        ['name' => 'Šunka', 'keywords' => 'šunka', 'variant_keywords' => null, 'exclude_keywords' => 'pizza bageta toust sendvič chips popcorn příchu maggi kočk pamlsk',
            'category' => ['Maso a lahůdky', 'Uzeniny a lahůdky', 'Šunky, slaniny a speciality']],
        ['name' => 'Párky', 'keywords' => 'párky|párek', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Maso a lahůdky', 'Uzeniny a lahůdky', 'Párky a špekáčky']],
        ['name' => 'Slanina', 'keywords' => 'slanina', 'variant_keywords' => null, 'exclude_keywords' => 'příchu chips brambůrk snack sendvič',
            'category' => ['Maso a lahůdky', 'Uzeniny a lahůdky', 'Šunky, slaniny a speciality']],
        ['name' => 'Losos', 'keywords' => 'losos', 'variant_keywords' => null, 'exclude_keywords' => 'kočk pet paštik pomazánk kapsičk krmiv želé pamlsk psy dreamies purina gourmet barkley vitakraft příchu paté krém',
            'category' => ['Maso a lahůdky', 'Maso, ryby a speciality', 'Ryby a mořské plody']],
        ['name' => 'Chléb', 'keywords' => 'chléb', 'variant_keywords' => null, 'exclude_keywords' => 'chlebíčk pita toust toastov strouhan krutony suchar mouka krevet',
            'category' => ['Pekárna', 'Slané pečivo a chléb']],
        ['name' => 'Rohlíky', 'keywords' => 'rohlík', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Pekárna', 'Volné pečivo', 'Slané pečivo', 'Rohlíky']],
        ['name' => 'Hladká mouka', 'keywords' => 'mouka hladká', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Trvanlivé', 'Mouka']],
        ['name' => 'Cukr', 'keywords' => 'cukr krystal|moučka|krupice', 'variant_keywords' => null, 'exclude_keywords' => 'vanil',
            'category' => ['Trvanlivé', 'Sůl, cukr a koření', 'Cukr']],
        ['name' => 'Rýže', 'keywords' => 'rýže', 'variant_keywords' => null, 'exclude_keywords' => 'radegast pivo ležák chlebíčk mléčná chipsy nápoj svačink předvařen bonduelle hipp dětská kubík',
            'category' => ['Trvanlivé', 'Rýže, těstoviny, luštěniny, gnocchi a zavářky', 'Rýže']],
        ['name' => 'Těstoviny', 'keywords' => 'těstoviny|špagety|penne|fusilli|spaghetti', 'variant_keywords' => null, 'exclude_keywords' => 'hami příkrm maggi omáčk salát zapečen hotov boloňsk vařené hipp kotányi bolognese carbonara',
            'category' => ['Trvanlivé', 'Rýže, těstoviny, luštěniny, gnocchi a zavářky', 'Těstoviny']],
        ['name' => 'Slunečnicový olej', 'keywords' => 'olej slunečnicový', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Trvanlivé', 'Oleje a Octy', 'Rostlinné oleje']],
        ['name' => 'Olivový olej', 'keywords' => 'olej olivový', 'variant_keywords' => null, 'exclude_keywords' => 'rama tuňák sardink',
            'category' => ['Trvanlivé', 'Oleje a Octy', 'Olivové oleje']],
        ['name' => 'Mletá káva', 'keywords' => 'káva mletá', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Nápoje', 'Káva a kávoviny', 'Mletá káva']],
        ['name' => 'Zrnková káva', 'keywords' => 'káva zrnková|zrna', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Nápoje', 'Káva a kávoviny', 'Zrnková káva']],
        ['name' => 'Milka', 'keywords' => 'milka', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Trvanlivé', 'Sladkosti a cukrovinky', 'Tabulkové čokolády']],
        ['name' => 'Nutella', 'keywords' => 'nutella', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Trvanlivé', 'Sladkosti a cukrovinky', 'Sladké krémy a pomazánky']],
        ['name' => 'Brambory', 'keywords' => 'brambory', 'variant_keywords' => null, 'exclude_keywords' => 'hami příkrm kotányi maggi mražen americk hranolky knedlík chips chipsy kaše placky salát krokety lupínky',
            'category' => ['Ovoce a zelenina', 'Zelenina', 'Brambory']],
        ['name' => 'Cibule', 'keywords' => 'cibule', 'variant_keywords' => null, 'exclude_keywords' => 'chips příchu kotányi granulovan polévk bistro burger snack směs kroužky smažen',
            'category' => ['Ovoce a zelenina', 'Zelenina', 'Česnek a cibule', 'Cibule']],
        ['name' => 'Rajčata', 'keywords' => 'rajčata|rajče', 'variant_keywords' => null, 'exclude_keywords' => 'racio loupan sušen protlak pasírovan kečup omáčk konzerv',
            'category' => ['Ovoce a zelenina', 'Zelenina', 'Rajčata, papriky a chilli', 'Rajčata']],
        ['name' => 'Okurka salátová', 'keywords' => 'okurk salátov|hadovk', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Ovoce a zelenina', 'Zelenina', 'Okurky']],
        ['name' => 'Paprika', 'keywords' => 'paprika|papriky', 'variant_keywords' => null, 'exclude_keywords' => 'plněn marinovan pomazánk omáčk pasta olivy rýže nudle těstoviny žervé kmotr sladká mletá paté lalok příkrm chips chipsy brambůrk křupk koření kotányi vitana sterilovan paprikáš snack příchu hermelín řezaná',
            'category' => ['Ovoce a zelenina', 'Zelenina', 'Rajčata, papriky a chilli', 'Papriky']],
        ['name' => 'Banány', 'keywords' => 'banány', 'variant_keywords' => null, 'exclude_keywords' => 'příchu orion tyčink oplatk svačink',
            'category' => ['Ovoce a zelenina', 'Ovoce', 'Banány a exotické ovoce']],
        ['name' => 'Jablka', 'keywords' => 'jablka', 'variant_keywords' => null, 'exclude_keywords' => 'příchu granátov',
            'category' => ['Ovoce a zelenina', 'Ovoce', 'Jablka a hrušky']],
        ['name' => 'Pomeranče', 'keywords' => 'pomeranče', 'variant_keywords' => null, 'exclude_keywords' => 'příchu šťáv kaše maska',
            'category' => ['Ovoce a zelenina', 'Ovoce', 'Citrusy', 'Pomeranče a mandarinky']],
        ['name' => 'Mandarinky', 'keywords' => 'mandarinky', 'variant_keywords' => null, 'exclude_keywords' => 'kompot nálev',
            'category' => ['Ovoce a zelenina', 'Ovoce', 'Citrusy', 'Pomeranče a mandarinky']],
        ['name' => 'Coca-Cola Zero', 'keywords' => 'coca cola', 'variant_keywords' => 'zero', 'exclude_keywords' => 'jack daniel bacardi rum whisky',
            'category' => ['Nápoje', 'Limonády a ledové čaje', 'Kolové nápoje bez cukru']],
        ['name' => 'Coca-Cola', 'keywords' => 'coca cola', 'variant_keywords' => null, 'exclude_keywords' => 'zero light jack daniel bacardi rum whisky',
            'category' => ['Nápoje', 'Limonády a ledové čaje', 'Kolové nápoje s cukrem']],
        ['name' => 'Pepsi', 'keywords' => 'pepsi', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Nápoje', 'Limonády a ledové čaje']],
        ['name' => 'Kofola', 'keywords' => 'kofola', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Nápoje', 'Limonády a ledové čaje']],
        ['name' => 'Pilsner Urquell', 'keywords' => 'pilsner urquell', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Nápoje', 'Pivo', '11-12 / Ležáky']],
        ['name' => 'Mattoni', 'keywords' => 'mattoni', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Nápoje', 'Vody, minerálky a funkční vody', 'Minerální vody']],
        ['name' => 'Toaletní papír', 'keywords' => 'toaletní papír', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Úklid', 'Papírové potřeby', 'Toaletní papír']],
        ['name' => 'Prací prostředek', 'keywords' => 'prací', 'variant_keywords' => null, 'exclude_keywords' => 'myčk',
            'category' => ['Úklid', 'Praní']],
        ['name' => 'Prostředek na nádobí', 'keywords' => 'nádobí', 'variant_keywords' => null, 'exclude_keywords' => 'myčk sušák kartáč houbičk',
            'category' => ['Úklid', 'Mytí nádobí', 'Ruční mytí']],
        ['name' => 'Zubní pasta', 'keywords' => 'zubní pasta', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Drogerie', 'Péče o ústa', 'Zubní pasty']],
        ['name' => 'Sprchový gel', 'keywords' => 'sprchový gel', 'variant_keywords' => null, 'exclude_keywords' => null,
            'category' => ['Drogerie', 'Mýdla, sprcha a koupel', 'Sprchové gely']],
    ];

    /**
     * Založí výchozí produkty (existující podle názvu přepíše) a přiřadí k nim nabídky.
     */
    public function run(AssignProducts $assign): void
    {
        foreach (self::PRODUCTS as $data) {
            $product = Product::query()->updateOrCreate(['name' => $data['name']], [
                'keywords' => $data['keywords'],
                'variant_keywords' => $data['variant_keywords'],
                'exclude_keywords' => $data['exclude_keywords'],
                'category_id' => $this->categoryId($data['category']),
            ]);
            $assign->forProduct($product);
        }
    }

    /**
     * Kategorie podle cesty názvů od oddělení; null, když strom není stažený nebo cesta neexistuje.
     *
     * @param  list<string>  $path
     */
    private function categoryId(array $path): ?int
    {
        $parentId = null;
        foreach ($path as $name) {
            $parentId = Category::query()->where('parent_id', $parentId)->where('name', $name)->value('id');
            if ($parentId === null) {
                return null;
            }
        }

        return $parentId;
    }
}
