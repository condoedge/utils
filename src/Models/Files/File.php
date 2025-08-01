<?php

namespace Condoedge\Utils\Models\Files;

use Carbon\Carbon;
use Condoedge\Utils\Models\Contracts\Searchable;
use Condoedge\Utils\Models\Files\FileVisibilityEnum;
use Condoedge\Utils\Models\Files\FileableFile;
use Condoedge\Utils\Models\Model;
use Condoedge\Utils\Models\Tags\MorphToManyTagsTrait;
use Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
use Condoedge\Utils\Models\Traits\BelongsToUserTrait;
use Condoedge\Utils\Models\Traits\HasSearchableNameTrait;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Facades\Image;
use Kompo\Core\FileHandler;

class File extends Model implements Searchable
{
    use BelongsToTeamTrait;
    use BelongsToUserTrait;
    use MorphToManyTagsTrait;

    use FileActionsKomponents;

    use HasSearchableNameTrait;

    public $fileType = 'file';

    public const SEARCHABLE_NAME_ATTRIBUTE = 'name';

    protected $casts = [
        'visibility' => FileVisibilityEnum::class,
    ];

    public function save(array $options = [])
    {
        $this->setTeamId();
        $this->setUserId();

        parent::save();
    }

    /* RELATIONS */
    public function fileable()
    {
        return $this->morphTo();
    }

    public function fileableFiles()
    {
        return $this->hasMany(FileableFile::class, 'file_id');
    }

    /* SCOPES */
    public function scopeHasFilename($query, $name = '')
    {
        return $query->where(
            fn($q) => $q->where('name', 'LIKE', wildcardSpace($name))
        );
    }

    public function scopeFileAttachedTo($query, $parentId, $parentType)
    {
        return $query->where('parent_type', $parentType)->where('parent_id', $parentId);
    }

    public function scopeGetLibrary($query, $filters = [])
    {
        $query = $query->with('fileable')->where('team_id', currentTeamId())->orderByDesc('created_at');

        if(array_key_exists('fileable_type', $filters) && $fileableType = $filters['fileable_type']) {
            $query = $query->where('fileable_type', $fileableType);
        }

        if(array_key_exists('filename', $filters)) {
            $query = $query->hasFilename($filters['filename']);
        }

        if (array_key_exists('mime_type', $filters) && $mimeType = $filters['mime_type']) {
            $mimeTypes = $this->iconMimeTypes();

            if($mimeType == 'far fa-file-alt'){ //other

                $excludeValues = collect($mimeTypes)->flatMap(fn($types) => $types)->toArray();

                $query = $query->whereNotIn('mime_type', $excludeValues);


            }else{

                $values = $mimeTypes[$mimeType];

                $query = $query->whereIn('mime_type', $values);

            }
        }

        if (array_key_exists('tags_and', $filters) && $tags = $filters['tags_and']) {
            foreach ($tags as $tagId) {
                $query = $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
            }
        }

        if(array_key_exists('year', $filters) && $year = $filters['year']) {
            $query = $query->whereRaw('YEAR(created_at) = ?', [$year]);
        }

        if(array_key_exists('month', $filters) && $yearMonth = $filters['month']) {
            $query = $query->whereRaw('LEFT(created_at, 7) = ?', [$yearMonth]);
        }

        return $query;
    }

    public function scopeForSubtype($query, $subtype)
    {
        $query->where('subtype', $subtype);
    }

    /* ATTRIBUTES */

    public function getDisplayFlAttribute()
    {
        return $this->name;
    }

    public function getLinkAttribute()
    {
        return \Storage::disk($this->disk)->url($this->path);
    }

    public function viewableForUser()
    {
        return $this->visibility == FileVisibilityEnum::PUBLIC || ($this->visibility == FileVisibilityEnum::PRIVATE && $this->user_id == auth()->id());
    }


    /* ACTIONS */
    public function delete()
    {
        $this->fileableFiles()->delete();

        parent::delete();
    }

    public static function getFileTypeRawQuery()
    {
        $rawCaseQuery = 'CASE ';
		
		foreach (FileTypeEnum::cases() as $case) {
			$mimeTypes = collect($case->mimeTypes())->map(function ($mime) {
				return "'$mime'";
			})->implode(',');

			if ($mimeTypes) {
				$rawCaseQuery .= "WHEN mime_type IN ({$mimeTypes}) THEN {$case->value} ";
			}
		}

		$rawCaseQuery .= 'ELSE ' .  FileTypeEnum::UNKNOWN->value . ' END';

        return $rawCaseQuery;
    }

    public static function uploadMultipleFiles($files, $fileableType = null, $fileableId = null, $tags = [])
    {
        $fileHandler = new FileHandler();

        $fileHandler->setDisk('public'); // TODO: make this configurable

        collect($files)->map(function ($uploadedFile) use ($fileHandler, $fileableId, $fileableType, $tags) {

            $file = new File();

            foreach ($fileHandler->fileToDB($uploadedFile, new File()) as $key => $value) {
                $file->{$key} = $value;
            }

            if ($fileableId && $fileableType) {
                $file->fileable_id = $fileableId;
                $file->fileable_type = $fileableType;
            }

            $file->team_id = currentTeam()->id;

            $file->save();

            if($tags && count($tags)) $file->tags()->sync($tags);

            return $file->id;
        });
    }

    public function resizeImage($width, $height)
    {
        $file = \Storage::disk($this->disk ?? 'public')->get($this->path);
        $format = pathinfo($this->path, PATHINFO_EXTENSION);

        $image = Image::make($file)->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->orientate()->encode($format);

        \Storage::disk($this->disk ?? 'public')->put($this->path, $image);
        \Storage::disk($this->disk ?? 'public')->setVisibility($this->path, 'public');
    }

    /* SCOPES */
    public function scopeUserOwnedRecords($query)
    {
        return $query->whereIn('fileable_type', config('kompo-utils.morphables-contact-associated-to-user', []))
            ->whereHas('fileable', function ($q) {
                $q->where(function($subquery) {
                    $model = $subquery->getModel();

                    if (Schema::hasColumn($model->getTable(), 'user_id')) {
                        $subquery->where('user_id', auth()->id());
                    } elseif (method_exists($model, 'user')) {
                        $subquery->whereHas('user', function($userQuery) {
                            $userQuery->where('id', auth()->id());
                        });
                    }
                });
            });
    }

    /* ELEMENTS */
    public function uploadedAt()
    {
        return _Html($this->created_at->translatedFormat('d M Y'))->class('text-gray-500 text-xs whitespace-nowrap');
    }

    public static function emptyPanel()
    {
        return _DashedBox('files-text-click-on-a-file', 'py-20 text-lg px-4');
    }

    public static function typesOptions()
    {
        return config('kompo-files.types');
    }

    public static function formattedTypesOptions()
    {
        return collect(static::typesOptions())->mapWithKeys(
            fn($label, $value) => [$value => ucfirst($label[0])]
        );
    }


    public static function fileFilters($titleKompo, $more = null)
    {
        return _Rows(
            _Flex(
                $titleKompo,

                $more,
            ),
            _Rows(

                _Rows(
//                    _TitleMini('attached-to')->class('mr-4'),
                    static::buttonGroup('parent_type_bis', false)
                        ->options(
                            collect(static::typesOptions())->mapWithKeys(
                                fn($label, $value) => static::selectOption($value, $label[0], $label[1])
                            )
                        )->selectedClass('!bg-info text-white', '')
                )->class('mb-4'),


            _Columns(
                _Rows(
                )->class('mb-4'),
                _Rows(
                    _Input()->icon('icon-search')->placeholder('general-search')
                        ->name('name', false)
                        ->class('mb-0')
                        ->type('search')
                        ->filter(),
                )->class('mb-4'),
            ),
            _Panel(
                static::yearlyMonthlyLinkGroup()
            )->id('file-manager-year-month-filter'),
            )->class('card bg-white rounded-2xl shadow-lg pt-6 pb-0 px-6 mb-8'),
        );
    }

    public static function yearlyMonthlyLinkGroup()
    {
        if ($year = request('year')) {
            return _Flex4(
                _Link(__('general-year').' '.$year)->class('text-greenmain font-bold')->icon('arrow-left')
                    ->getElements('getYearsMonthsFilter')->inPanel('file-manager-year-month-filter'),
                _LinkGroup()->name('month', false)->class('mb-0')
                    ->options(
                        static::getFilesCountFor($year)->mapWithKeys(fn($stat) => [
                            $stat->label => static::yearMonthOption(Carbon::createFromFormat('Y-m-d', $stat->label.'-01')->translatedFormat('M'), $stat->cn)
                        ])
                    )->selectedClass('text-level3 border-b-2 border-level3', 'text-level3 border-b-2 border-transparent')
                    ->filter()
            )->class('mb-4');
        }

        return _Flex4(
            _Html('general-filter-by-year')->class('text-greenmain font-medium'),
            _LinkGroup()->name('year', false)->class('mb-0')
                ->options(
                    static::getFilesCountFor()->mapWithKeys(fn($stat) => [
                        $stat->label => static::yearMonthOption($stat->label, $stat->cn)
                    ])
                )->selectedClass('text-greenmain border-b-2 border-greenmain', 'text-greenmain border-b-2 border-transparent')
                ->filter()
                ->onSuccess(fn($e) => $e->getElements('getYearsMonthsFilter')->inPanel('file-manager-year-month-filter'))
        )->class('mb-4');
    }

    protected static function getFilesCountFor($year = null)
    {
        $labelFunc = $year ? 'LEFT(created_at,7)' : 'YEAR(created_at)';

        $query = static::selectRaw($labelFunc.' as label, COUNT(*) as cn')->where('team_id', currentTeam()?->id)
            ->groupByRaw($labelFunc)->orderByRaw($labelFunc.' DESC');

        return ($year ? $query->whereRaw('YEAR(created_at) = ?', [$year]) : $query )->get();
    }

    protected static function yearMonthOption($label, $count)
    {
        return _Html($label.' <span class="text-xs text-gray-600">('.$count.')</span>')->class('font-bold cursor-pointer mr-4');
    }

    protected static function buttonGroup($name, $interactsWithModel = true)
    {
        return _ButtonGroup()->name($name, $interactsWithModel)
            ->class('mb-0')
            ->containerClass('row no-gutters')
            ->optionClass('w-20 lg:w-24')
            ->selectedClass('bg-info text-greenmain', 'text-greenmain')
            ->filter();
    }

    protected static function selectOption($value, $label, $icon = null, $iconSvg = true)
    {
        $icon = $icon ? ($iconSvg ? _Sax($icon) : _I()->class($icon)) : null;

        $label = _HtmlSax('<br><div class="truncate">'.$label.'</div>')
            ->class('justify-center p-2 text-xs font-bold cursor-pointer');

        return [
            $value => _Rows(
                $icon ? $label->icon($icon->class('text-lg')) : $label
            )
        ];
    }

    public static function fileUploadLinkAndBox($name, $toggleOnLoad = true, $fileIds = [])
    {
        return _FileUploadLinkAndBox($name, $toggleOnLoad, $fileIds, static::getMaxFilesSize());
    }

    public static function attachmentsRules($defaultKey = 'attachments')
    {
        $maxFilesSize = static::getMaxFilesSize() * 1000;

        return [
			$defaultKey.'.*' => ['max:' . $maxFilesSize, new \Condoedge\Utils\Rule\FileMimeTypeRule()],
			$defaultKey => [new \Condoedge\Utils\Rule\FilesTotalUploadSize($maxFilesSize), 'max:20'],
		];
    }

    public static function getMaxFilesSize()
    {
        return config('kompo-utils.max-files-size', 20);
    }

    public function linkEl()
    {
        return _Link($this->name)->class('mt-1 -mr-2')->col('col-md-3')
            ->icon('arrow-down');
    }

    /* SEARCHS */
    public function scopeSearch($query, $search)
    {
        return $query->forTeam(currentTeamId())
            ->searchName($search);
    }

    public function searchElement($file, $search)
    {
        return _SearchResult(
            $search,
            $file->display_fl,
            [
                $file->uploadedAt(),
            ],
        )->redirect('file.page', ['id' => $file->id]);
    }
}
