<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\DetectionCode;
use App\Models\Detection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class SurveysController extends Controller
{
    /**
     * Submit survey data
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'detection_code_id' => 'required|integer|exists:detection_codes,id',

            // Q1. 填表时间
            'fill_date' => 'required|date',
            'fill_time' => 'required|date_format:H:i',

            // Q2. 场主姓名
            'owner_name' => 'required|string|max:100',

            // Q3. 蜂场当前地址
            'location_name' => 'nullable|string|max:500',
            'location_latitude' => 'nullable|numeric|between:-90,90',
            'location_longitude' => 'nullable|numeric|between:-180,180',

            // Q4. 联系手机号
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',

            // Q5. 蜂群数量（群）
            'bee_count' => 'required|integer|min:1',

            // Q6. 饲养方式
            'raise_method' => 'required|in:定地,省内小转地,跨省大转地',

            // Q7. 蜂种
            'bee_species' => 'required|in:中华蜜蜂,西方蜜蜂（意大利蜜蜂等）',

            // Q8. 蜂场收入来源排序（1-4）
            'income_ranks' => 'required|array',
            'income_ranks.honey' => 'required|in:1,2,3,4',
            'income_ranks.royalJelly' => 'required|in:1,2,3,4',
            'income_ranks.pollination' => 'required|in:1,2,3,4',
            'income_ranks.sellBee' => 'required|in:1,2,3,4',

            // Q9. 当前是否为生产期
            'is_production_now' => 'required|in:是,否',

            // Q10-Q12. 条件字段（当Q9=是时）
            'product_type' => 'nullable|in:蜂蜜,花粉,蜂王浆,其他',
            'honey_type' => 'nullable|string|max:100',
            'pollen_type' => 'nullable|string|max:100',

            // Q13. 下一个生产期开始时间
            'next_month' => 'required|string|max:100',

            // Q14-Q16. 条件字段
            'need_move' => 'nullable|in:是,否',
            'move_province' => 'nullable|string|max:50',
            'move_city' => 'nullable|string|max:50',
            'move_district' => 'nullable|string|max:50',
            'next_floral' => 'nullable|string|max:200',

            // Q17. 近一个月内是否有蜂群异常
            'has_abnormal' => 'required|in:是,否',

            // Q18-Q23. 条件字段（当Q17=是时）
            'sick_ages' => 'nullable|array',
            'sick_ages.*' => 'string|in:幼虫,成蜂',
            'sick_count' => 'nullable|integer|min:1',
            'symptoms' => 'nullable|array',
            'symptoms.*' => 'string',
            'symptom_other' => 'nullable|string|max:500',
            'medications' => 'nullable|array',
            'medications.*' => 'string|max:200',
            'occur_rule' => 'nullable|string|max:200',
            'possible_reason' => 'nullable|string|max:200',

            // Q24. 往年蜂群集中发病的时间段（可多选）
            'past_months' => 'required|array|min:1',
            'past_months.*' => 'string|in:1,2,3,4,5,6,7,8,9,10,11,12',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($request, $user) {
            // 验证检测码是否属于当前用户且状态正确
            $detectionCode = DetectionCode::where('id', $request->detection_code_id)
                ->where('assigned_user_id', $user->id)
                ->where('status', 'assigned')
                ->lockForUpdate()
                ->first();

            if (!$detectionCode) {
                throw ValidationException::withMessages([
                    'detection_code_id' => ['检测号不存在、不属于当前用户或状态异常'],
                ]);
            }

            // 验证收入排序不能重复
            $ranks = array_values($request->income_ranks);
            if (count($ranks) !== count(array_unique($ranks))) {
                throw ValidationException::withMessages([
                    'income_ranks' => ['收入来源排名不能重复'],
                ]);
            }

            // 条件验证：生产期相关
            if ($request->is_production_now === '是') {
                if (!$request->product_type) {
                    throw ValidationException::withMessages([
                        'product_type' => ['当前为生产期时，必须选择主要蜂产品种类'],
                    ]);
                }

                if ($request->product_type === '蜂蜜' && !$request->honey_type) {
                    throw ValidationException::withMessages([
                        'honey_type' => ['选择蜂蜜时，必须输入蜂蜜种类'],
                    ]);
                }

                if ($request->product_type === '花粉' && !$request->pollen_type) {
                    throw ValidationException::withMessages([
                        'pollen_type' => ['选择花粉时，必须输入花粉种类'],
                    ]);
                }
            }

            // 条件验证：转地相关
            $isNotLastPeriod = $request->next_month !== '没有或已是当年最后一个生产期';
            if ($isNotLastPeriod) {
                if (!$request->need_move) {
                    throw ValidationException::withMessages([
                        'need_move' => ['请选择是否需要转地'],
                    ]);
                }

                if ($request->need_move === '是') {
                    if (!$request->move_province || !$request->move_city || !$request->move_district) {
                        throw ValidationException::withMessages([
                            'move_destination' => ['转地时必须选择完整的省市县'],
                        ]);
                    }
                } elseif ($request->need_move === '否') {
                    if (!$request->next_floral) {
                        throw ValidationException::withMessages([
                            'next_floral' => ['不转地时必须输入主要蜜粉源'],
                        ]);
                    }
                }
            }

            // 条件验证：异常情况相关
            if ($request->has_abnormal === '是') {
                if (!$request->sick_ages || count($request->sick_ages) === 0) {
                    throw ValidationException::withMessages([
                        'sick_ages' => ['有蜂群异常时必须选择发病虫龄'],
                    ]);
                }

                if (!$request->sick_count) {
                    throw ValidationException::withMessages([
                        'sick_count' => ['有蜂群异常时必须输入发病蜂群数'],
                    ]);
                }

                if (!$request->symptoms || count($request->symptoms) === 0) {
                    throw ValidationException::withMessages([
                        'symptoms' => ['有蜂群异常时必须选择主要症状'],
                    ]);
                }

                if (in_array('其他', $request->symptoms) && !$request->symptom_other) {
                    throw ValidationException::withMessages([
                        'symptom_other' => ['选择其他症状时必须输入具体说明'],
                    ]);
                }

                if (!$request->occur_rule) {
                    throw ValidationException::withMessages([
                        'occur_rule' => ['有蜂群异常时必须选择发生规律'],
                    ]);
                }

                if (!$request->possible_reason) {
                    throw ValidationException::withMessages([
                        'possible_reason' => ['有蜂群异常时必须选择可能原因'],
                    ]);
                }
            }

            // 创建问卷记录
            $survey = Survey::create([
                'user_id' => $user->id,
                'detection_code_id' => $detectionCode->id,
                'fill_date' => $request->fill_date,
                'fill_time' => $request->fill_time,
                'owner_name' => $request->owner_name,
                'location_name' => $request->location_name,
                'location_latitude' => $request->location_latitude,
                'location_longitude' => $request->location_longitude,
                'phone' => $request->phone,
                'bee_count' => $request->bee_count,
                'raise_method' => $request->raise_method,
                'bee_species' => $request->bee_species,
                'income_ranks' => $request->income_ranks,
                'is_production_now' => $request->is_production_now,
                'product_type' => $request->product_type,
                'honey_type' => $request->honey_type,
                'pollen_type' => $request->pollen_type,
                'next_month' => $request->next_month,
                'need_move' => $request->need_move,
                'move_province' => $request->move_province,
                'move_city' => $request->move_city,
                'move_district' => $request->move_district,
                'next_floral' => $request->next_floral,
                'has_abnormal' => $request->has_abnormal,
                'sick_ages' => $request->sick_ages,
                'sick_count' => $request->sick_count,
                'symptoms' => $request->symptoms,
                'symptom_other' => $request->symptom_other,
                'medications' => $request->medications,
                'occur_rule' => $request->occur_rule,
                'possible_reason' => $request->possible_reason,
                'past_months' => $request->past_months,
                'submitted_at' => now(),
                'status' => 'submitted',
            ]);

            // 更新检测码状态为已使用
            $detectionCode->update([
                'status' => 'used',
                'used_at' => now(),
            ]);

            // 创建或补全 Detection 记录（幂等：同一检测码仅一条）
            $existing = Detection::query()->where('detection_code_id', $detectionCode->id)->first();
            if (!$existing) {
                Detection::query()->create([
                    'user_id' => $user->id,
                    'detection_code_id' => $detectionCode->id,
                    'sample_no' => null, // 由实验室后续补录
                    'status' => 'pending',
                    'submitted_at' => now(),
                    'contact_phone' => $request->phone,
                    'address_text' => $request->location_name,
                ]);
            } else {
                // 保守补齐：不覆盖已有重要字段
                $patch = [];
                if (empty($existing->submitted_at)) $patch['submitted_at'] = now();
                if (empty($existing->contact_phone)) $patch['contact_phone'] = $request->phone;
                if (empty($existing->address_text) && !empty($request->location_name)) $patch['address_text'] = $request->location_name;
                if (!empty($patch)) {
                    $existing->update($patch);
                }
            }

            return response()->json([
                'success' => true,
                'message' => '问卷提交成功',
                'data' => [
                    'survey_id' => $survey->id,
                    'detection_code' => $detectionCode->prefix . $detectionCode->code,
                    'submitted_at' => $survey->submitted_at->format('Y-m-d H:i:s'),
                ],
            ], 201);
        });
    }
}
