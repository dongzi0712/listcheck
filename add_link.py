import requests
import json

class NetdiskAPI:
    def __init__(self, base_url, api_key):
        """
        初始化API客户端
        :param base_url: API基础URL，如 http://your-domain
        :param api_key: API密钥
        """
        self.base_url = base_url.rstrip('/')
        self.headers = {
            'X-API-Key': api_key,
            'Content-Type': 'application/json'
        }
    
    def add_link(self, title, url, platform):
        """
        添加网盘链接
        :param title: 分享标题
        :param url: 分享链接
        :param platform: 网盘平台(baidu/quark/aliyun/other)
        :return: 新添加的链接ID
        """
        api_url = f"{self.base_url}/api.php?action=add"
        data = {
            'title': title,
            'url': url,
            'platform': platform
        }
        
        try:
            response = requests.post(api_url, headers=self.headers, json=data)
            response.raise_for_status()  # 检查HTTP状态码
            
            result = response.json()
            if result['success']:
                return result['data']['id']
            else:
                raise Exception(result['message'])
                
        except requests.exceptions.RequestException as e:
            raise Exception(f"请求失败: {str(e)}")
        except json.JSONDecodeError:
            raise Exception("解析响应失败")

# 使用示例
if __name__ == '__main__':
    # 配置信息
    API_BASE_URL = 'http://your-domain'  # 替换为你的域名
    API_KEY = 'your-api-key'  # 替换为你的API密钥
    
    # 创建API客户端
    client = NetdiskAPI(API_BASE_URL, API_KEY)
    
    # 示例1：添加百度网盘链接
    try:
        link_id = client.add_link(
            title="测试资源",
            url="https://pan.baidu.com/s/xxxxx",
            platform="baidu"
        )
        print(f"链接添加成功，ID: {link_id}")
    except Exception as e:
        print(f"添加失败: {str(e)}")
    
    # 示例2：批量添加链接
    links = [
        {
            'title': '资源1',
            'url': 'https://pan.baidu.com/s/xxxxx',
            'platform': 'baidu'
        },
        {
            'title': '资源2',
            'url': 'https://pan.quark.cn/s/xxxxx',
            'platform': 'quark'
        }
    ]
    
    for link in links:
        try:
            link_id = client.add_link(
                title=link['title'],
                url=link['url'],
                platform=link['platform']
            )
            print(f"链接 {link['title']} 添加成功，ID: {link_id}")
        except Exception as e:
            print(f"添加 {link['title']} 失败: {str(e)}") 