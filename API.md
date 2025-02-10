# 网盘链接管理系统 API文档

## 认证方式

所有API请求都需要在请求头中包含 `X-API-Key` 字段，值为系统生成的API密钥。

## 接口列表

### 添加链接 
POST /api.php?action=add

请求体:
{
"title": "分享标题",
"url": "分享链接",
"platform": "网盘平台(baidu/quark/aliyun/other)"
}

响应:
{
"success": true,
"data": {
"id": "新添加的链接ID"
},
"message": "链接添加成功"
}

### 删除链接
DELETE /api.php?action=delete&id={链接ID}

响应:
json
{
"success": true,
"data": null,
"message": "链接删除成功"
}

### 获取链接列表
GET /api.php?action=list&page={页码}&limit={每页数量}

参数:
- page: 页码，默认1
- limit: 每页数量，默认20，最大100

响应:
json
{
"success": true,
"data": {
"total": "总记录数",
"page": "当前页码",
"limit": "每页数量",
"links": [
{
"id": "链接ID",
"title": "分享标题",
"url": "分享链接",
"platform": "网盘平台",
"status": "状态",
"last_check_time": "最后检查时间",
"create_time": "创建时间",
"update_time": "更新时间"
}
]
}
}

### 获取单个链接
GET /api.php?action=get&id={链接ID}

响应:
json
{
"success": true,
"data": {
"id": "链接ID",
"title": "分享标题",
"url": "分享链接",
"platform": "网盘平台",
"status": "状态",
"last_check_time": "最后检查时间",
"create_time": "创建时间",
"update_time": "更新时间"
}
}

## 错误响应

当请求失败时，响应格式如下:

json
{
"success": false,
"data": null,
"message": "错误信息"
}

常见错误:
- 无效的API密钥
- 缺少必要参数
- 链接不存在
- 不支持的请求方法

## 注意事项

1. API密钥不要泄露给不信任的第三方
2. 建议使用HTTPS协议调用API
3. 添加链接时会自动检查链接有效性
4. 获取链接列表支持分页，建议合理设置每页数量
