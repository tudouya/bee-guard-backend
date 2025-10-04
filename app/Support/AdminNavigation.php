<?php

namespace App\Support;

class AdminNavigation
{
    public const GROUP_DETECTION_OPERATIONS = '检测运营';
    public const GROUP_PAYMENT = '订单与支付';
    public const GROUP_RECOMMENDATION = '推荐配置';
    public const GROUP_KNOWLEDGE = '知识字典';
    public const GROUP_COMMUNITY = '社区运营';
    public const GROUP_REWARDS = '奖励中心';
    public const GROUP_SYSTEM = '系统管理';

    public const ORDER_DETECTIONS = 11;
    public const ORDER_DETECTION_CODES = 12;
    public const ORDER_SURVEYS = 13;
    public const ORDER_SHIPPING = 14;

    public const ORDER_ORDERS = 21;
    public const ORDER_PAYMENT_PROOFS = 22;

    public const ORDER_RECOMMENDATION_RULES = 31;
    public const ORDER_PRODUCTS = 32;

    public const ORDER_DISEASES = 41;
    public const ORDER_KNOWLEDGE_ARTICLES = 42;

    public const ORDER_COMMUNITY_POSTS = 51;
    public const ORDER_COMMUNITY_REPLIES = 52;

    public const ORDER_COUPON_TEMPLATES = 61;
    public const ORDER_REWARD_RULES = 62;
    public const ORDER_REWARD_ISSUANCES = 63;

    public const ORDER_USERS = 71;
    public const ORDER_ENTERPRISES = 72;
}
